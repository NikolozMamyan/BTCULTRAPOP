<?php

namespace App\Tests\Service;

use App\Entity\Category;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Product;
use App\Enum\CartStatus;
use App\Enum\OrderStatus;
use App\Service\CartManager;
use App\Service\OrderCartRecoveryManager;
use App\Service\OrderManager;
use App\Service\StripeCheckoutService;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class OrderCartRecoveryManagerTest extends TestCase
{
    public function testReopenRestoresAndMergesTheCartThenClosesTheOldOrder(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->expects(self::once())
            ->method('wrapInTransaction')
            ->willReturnCallback(static fn (callable $callback): mixed => $callback());
        $entityManager
            ->expects(self::once())
            ->method('refresh')
            ->with(self::isInstanceOf(Order::class), LockMode::PESSIMISTIC_WRITE);

        $cartManager = new CartManager();
        $originalCart = $cartManager->createCart(token: 'original');
        $activeCart = $cartManager->createCart(token: 'active');
        $firstProduct = $this->product('Produit original', 'RECOVERY-1');
        $secondProduct = $this->product('Produit ajouté', 'RECOVERY-2');
        $cartManager->addProduct($originalCart, $firstProduct, 1);
        $cartManager->addProduct($activeCart, $secondProduct, 2);
        $originalCart->markConverted();

        $order = (new Order())
            ->setCart($originalCart)
            ->setOrderNumber('UP-RECOVERY-1');
        $manager = new OrderCartRecoveryManager(
            $entityManager,
            (new \ReflectionClass(StripeCheckoutService::class))->newInstanceWithoutConstructor(),
            new OrderManager(),
            $cartManager,
        );

        $result = $manager->reopen($order, $activeCart);

        self::assertSame($originalCart, $result);
        self::assertSame(CartStatus::ACTIVE, $originalCart->getStatus());
        self::assertSame(CartStatus::ABANDONED, $activeCart->getStatus());
        self::assertSame(2, $originalCart->getItems()->count());
        self::assertSame(3, $originalCart->getTotalQuantity());
        self::assertSame(OrderStatus::CANCELLED, $order->getStatus());
        self::assertSame(Order::PAYMENT_FAILURE_CART_REOPENED, $order->getPaymentFailureReason());
    }

    public function testReopenRebuildsALegacyOrderWithoutACartLink(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->method('wrapInTransaction')
            ->willReturnCallback(static fn (callable $callback): mixed => $callback());
        $entityManager->expects(self::once())->method('refresh');
        $entityManager->expects(self::once())->method('persist');
        $product = $this->product('Ancien produit', 'LEGACY-1');
        $order = (new Order())->setOrderNumber('UP-LEGACY-1');
        $order->addItem(
            (new OrderItem())
                ->setProduct($product)
                ->setProductName($product->getName())
                ->setQuantity(2)
                ->setUnitPriceTaxExcludedCents(1000)
                ->setUnitPriceTaxIncludedCents(1200),
        );
        $manager = new OrderCartRecoveryManager(
            $entityManager,
            (new \ReflectionClass(StripeCheckoutService::class))->newInstanceWithoutConstructor(),
            new OrderManager(),
            new CartManager(),
        );

        $cart = $manager->reopen($order);

        self::assertSame($cart, $order->getCart());
        self::assertSame(CartStatus::ACTIVE, $cart->getStatus());
        self::assertSame(2, $cart->getTotalQuantity());
        self::assertSame(OrderStatus::CANCELLED, $order->getStatus());
    }

    private function product(string $name, string $reference): Product
    {
        return (new Product())
            ->setName($name)
            ->setReference($reference)
            ->setCategory((new Category())->setName('Test'))
            ->setPriceTaxExcluded('10.000000')
            ->setPriceTaxIncluded('12.000000')
            ->setQuantity(10);
    }
}
