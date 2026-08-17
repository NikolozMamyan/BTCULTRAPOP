<?php

namespace App\Tests\Entity;

use App\Entity\Order;
use App\Enum\OrderStatus;
use App\Enum\PaymentStatus;
use PHPUnit\Framework\TestCase;

final class OrderPaymentRecoveryStateTest extends TestCase
{
    public function testAFailedPaymentIsNotACommercialCancellation(): void
    {
        $order = new Order();
        $order->cancel(new \DateTimeImmutable('2026-08-17 10:00:00'));

        $order->markPaymentFailed('stripe.checkout_session_expired');

        self::assertSame(OrderStatus::PENDING_PAYMENT, $order->getStatus());
        self::assertSame(PaymentStatus::FAILED, $order->getPaymentStatus());
        self::assertNull($order->getCancelledAt());
    }

    public function testPaymentReminderSendsAreTracked(): void
    {
        $order = new Order();
        $firstSend = new \DateTimeImmutable('2026-08-17 11:00:00');
        $secondSend = new \DateTimeImmutable('2026-08-18 12:00:00');

        $order->markPaymentReminderSent($firstSend);
        $order->markPaymentReminderSent($secondSend);

        self::assertSame(2, $order->getPaymentReminderCount());
        self::assertSame($secondSend, $order->getPaymentReminderSentAt());
    }

    public function testStartingANewPaymentClearsTheOldCancellationDate(): void
    {
        $order = new Order();
        $order->cancel(new \DateTimeImmutable('2026-08-17 10:00:00'));

        $order->markPaymentProcessing();

        self::assertSame(OrderStatus::PENDING_PAYMENT, $order->getStatus());
        self::assertSame(PaymentStatus::PROCESSING, $order->getPaymentStatus());
        self::assertNull($order->getCancelledAt());
    }

    public function testReopenedCartReasonIsStoredOnTheCancelledOrder(): void
    {
        $order = new Order();

        $order->cancel(reason: Order::PAYMENT_FAILURE_CART_REOPENED);

        self::assertSame(OrderStatus::CANCELLED, $order->getStatus());
        self::assertSame(PaymentStatus::FAILED, $order->getPaymentStatus());
        self::assertSame(Order::PAYMENT_FAILURE_CART_REOPENED, $order->getPaymentFailureReason());
    }
}
