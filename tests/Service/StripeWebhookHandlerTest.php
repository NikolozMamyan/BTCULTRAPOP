<?php

namespace App\Tests\Service;

use App\Entity\Order;
use App\Enum\OrderStatus;
use App\Enum\PaymentStatus;
use App\Repository\OrderRepository;
use App\Repository\StripeWebhookEventRepository;
use App\Service\OrderManager;
use App\Service\StripeConfigProvider;
use App\Service\StripeWebhookHandler;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;

final class StripeWebhookHandlerTest extends TestCase
{
    public function testExpiredCurrentSessionBecomesFailedInsteadOfCancelled(): void
    {
        $order = $this->orderWithId(42)->setStripeCheckoutSessionId('cs_current');
        $handler = $this->handlerFor($order);

        $result = $handler->synchronizeCheckoutSession((object) [
            'id' => 'cs_current',
            'payment_status' => 'unpaid',
            'metadata' => ['order_id' => '42'],
        ], 'checkout.session.expired');

        self::assertSame($order, $result);
        self::assertSame(OrderStatus::PENDING_PAYMENT, $order->getStatus());
        self::assertSame(PaymentStatus::FAILED, $order->getPaymentStatus());
        self::assertSame('stripe.checkout_session_expired', $order->getPaymentFailureReason());
        self::assertNull($order->getCancelledAt());
    }

    public function testExpiredStaleSessionCannotCancelCurrentRetry(): void
    {
        $order = $this->orderWithId(42)
            ->setStripeCheckoutSessionId('cs_current')
            ->markPaymentProcessing();
        $handler = $this->handlerFor($order);

        $result = $handler->synchronizeCheckoutSession((object) [
            'id' => 'cs_old',
            'payment_status' => 'unpaid',
            'metadata' => ['order_id' => '42'],
        ], 'checkout.session.expired');

        self::assertNull($result);
        self::assertSame('cs_current', $order->getStripeCheckoutSessionId());
        self::assertSame(OrderStatus::PENDING_PAYMENT, $order->getStatus());
        self::assertSame(PaymentStatus::PROCESSING, $order->getPaymentStatus());
    }

    public function testPaidStaleSessionIsStillHonoredOnce(): void
    {
        $order = $this->orderWithId(42)
            ->setStripeCheckoutSessionId('cs_current')
            ->markPaymentProcessing();
        $handler = $this->handlerFor($order);

        $result = $handler->synchronizeCheckoutSession((object) [
            'id' => 'cs_old',
            'payment_status' => 'paid',
            'payment_intent' => 'pi_paid',
            'metadata' => ['order_id' => '42'],
        ]);

        self::assertSame($order, $result);
        self::assertSame('cs_old', $order->getStripeCheckoutSessionId());
        self::assertSame('pi_paid', $order->getStripePaymentIntentId());
        self::assertSame(OrderStatus::PAID, $order->getStatus());
        self::assertSame(PaymentStatus::PAID, $order->getPaymentStatus());
    }

    private function handlerFor(Order $order): StripeWebhookHandler
    {
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('wrapInTransaction')->willReturnCallback(
            static fn (callable $operation): mixed => $operation(),
        );
        $entityManager->method('getClassMetadata')->willReturn(new ClassMetadata(Order::class));
        $entityManager->method('find')->willReturnCallback(
            static fn (string $className, mixed $id): ?Order => Order::class === $className && 42 === $id ? $order : null,
        );
        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($entityManager);
        $orders = new OrderRepository($registry);

        return new StripeWebhookHandler(
            (new \ReflectionClass(StripeConfigProvider::class))->newInstanceWithoutConstructor(),
            (new \ReflectionClass(StripeWebhookEventRepository::class))->newInstanceWithoutConstructor(),
            $orders,
            new OrderManager(),
            $entityManager,
        );
    }

    private function orderWithId(int $id): Order
    {
        $order = new Order();
        $property = new \ReflectionProperty(Order::class, 'id');
        $property->setValue($order, $id);

        return $order;
    }
}
