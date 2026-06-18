<?php

namespace App\Tests\Service;

use App\Entity\Address;
use App\Entity\Category;
use App\Entity\License;
use App\Entity\Product;
use App\Entity\ProductImage;
use App\Entity\User;
use App\Enum\CartStatus;
use App\Enum\OrderStatus;
use App\Enum\PaymentStatus;
use App\Model\CheckoutAddress;
use App\Service\CartManager;
use App\Service\OrderManager;
use App\Service\ShippingRateCalculator;
use PHPUnit\Framework\TestCase;

final class CartOrderManagerTest extends TestCase
{
    public function testCartManagerAddsProductsAndKeepsAmountsInCents(): void
    {
        $cartManager = new CartManager();
        $cart = $cartManager->createCart(token: 'cart-token');
        $product = $this->createProduct()
            ->setPriceTaxExcluded('49.916667')
            ->setPriceTaxIncluded('59.900000');

        $item = $cartManager->addProduct($cart, $product, 2);
        $sameItem = $cartManager->addProduct($cart, $product, 1);

        self::assertSame($item, $sameItem);
        self::assertSame('cart-token', $cart->getToken());
        self::assertSame(1, $cart->getItems()->count());
        self::assertSame(3, $item->getQuantity());
        self::assertSame(4992, $item->getUnitPriceTaxExcludedCents());
        self::assertSame(5990, $item->getUnitPriceTaxIncludedCents());
        self::assertSame(3, $cart->getTotalQuantity());
        self::assertSame(14976, $cart->getTotalTaxExcludedCents());
        self::assertSame(17970, $cart->getTotalTaxIncludedCents());
    }

    public function testCartManagerRejectsConvertedCart(): void
    {
        $cartManager = new CartManager();
        $cart = $cartManager->createCart()->markConverted();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('cart.error.not_active');

        $cartManager->addProduct($cart, $this->createProduct());
    }

    public function testCartManagerMergesSourceCartIntoCanonicalCart(): void
    {
        $cartManager = new CartManager();
        $canonical = $cartManager->createCart(token: 'canonical-cart');
        $source = $cartManager->createCart(token: 'device-cart');
        $firstProduct = $this->createProduct()
            ->setPriceTaxExcluded('49.916667')
            ->setPriceTaxIncluded('59.900000');
        $secondProduct = $this->createProduct()
            ->setName('Statuette Premium One Piece')
            ->setReference('ULTRA-002')
            ->setEan('9876543210987')
            ->setPriceTaxExcluded('74.916667')
            ->setPriceTaxIncluded('89.900000');

        $cartManager->addProduct($canonical, $firstProduct, 1);
        $cartManager->addProduct($source, $firstProduct, 2);
        $cartManager->addProduct($source, $secondProduct, 1);

        $cartManager->merge($source, $canonical);

        self::assertSame(CartStatus::ACTIVE, $canonical->getStatus());
        self::assertSame(CartStatus::ABANDONED, $source->getStatus());
        self::assertSame(0, $source->getItems()->count());
        self::assertSame(2, $canonical->getItems()->count());
        self::assertSame(4, $canonical->getTotalQuantity());
        self::assertSame(26960, $canonical->getTotalTaxIncludedCents());
        self::assertSame(3, $canonical->getItemForProduct($firstProduct)?->getQuantity());
        self::assertSame(1, $canonical->getItemForProduct($secondProduct)?->getQuantity());
    }

    public function testOrderManagerCreatesSnapshotAndMarksPayment(): void
    {
        $user = (new User())
            ->setEmail('CLIENT@example.com')
            ->setFirstName('Niko')
            ->setLastName('Ultrapop');
        $address = (new Address())
            ->setName('Maison')
            ->setStreet('10 rue de Paris')
            ->setPostalCode('75001')
            ->setCity('Paris')
            ->setCountryCode('FR')
            ->setPhone('0600000000');
        $product = $this->createProduct()
            ->setPriceTaxExcluded('10.000000')
            ->setPriceTaxIncluded('12.000000')
            ->setQuantity(5)
            ->addImage((new ProductImage())->setPath('/uploads/products/cover.webp')->setCover(true));
        $cartManager = new CartManager();
        $cart = $cartManager->createCart($user, 'checkout-token');
        $cartManager->addProduct($cart, $product, 2);

        $orderManager = new OrderManager();
        $order = $orderManager->createFromCart(
            cart: $cart,
            user: $user,
            shippingAddress: $address,
            shippingAmountTaxIncludedCents: 490,
            discountAmountTaxIncludedCents: 100,
            orderNumber: 'UP-TEST-0001',
        );

        self::assertSame(CartStatus::CONVERTED, $cart->getStatus());
        self::assertSame('UP-TEST-0001', $order->getOrderNumber());
        self::assertSame(OrderStatus::PENDING_PAYMENT, $order->getStatus());
        self::assertSame(PaymentStatus::PENDING, $order->getPaymentStatus());
        self::assertSame('client@example.com', $order->getCustomerEmail());
        self::assertSame('Niko Ultrapop', $order->getCustomerName());
        self::assertSame('Maison', $order->getShippingName());
        self::assertSame(2000, $order->getTotalTaxExcludedCents());
        self::assertSame(2790, $order->getTotalTaxIncludedCents());
        self::assertSame(27, $order->getLoyaltyPointsEarned());
        self::assertSame(1, $order->getItems()->count());

        $orderItem = $order->getItems()->first();

        self::assertSame('Figurine Collector Arcane', $orderItem->getProductName());
        self::assertSame('ULTRA-001', $orderItem->getProductReference());
        self::assertSame('0123456789012', $orderItem->getProductEan());
        self::assertSame('/uploads/products/cover.webp', $orderItem->getProductImage());
        self::assertSame('Figurines', $orderItem->getCategoryName());
        self::assertSame('Arcane', $orderItem->getLicenseName());
        self::assertSame('20.00', $orderItem->getTaxRate());

        $orderManager->markPaid($order, new \DateTimeImmutable('2026-06-16 10:00:00'));

        self::assertSame(OrderStatus::PAID, $order->getStatus());
        self::assertSame(PaymentStatus::PAID, $order->getPaymentStatus());
        self::assertSame(27, $user->getLoyaltyPoints());
        self::assertSame(3, $product->getQuantity());

        $orderManager->markPaid($order);

        self::assertSame(27, $user->getLoyaltyPoints());
        self::assertSame(3, $product->getQuantity());
    }

    public function testOrderManagerCreatesGuestOrderWithoutCustomerEmail(): void
    {
        $address = new CheckoutAddress();
        $address->name = 'Client Invite';
        $address->street = '20 rue de Lyon';
        $address->postalCode = '69001';
        $address->city = 'Lyon';
        $address->countryCode = 'FR';
        $address->phone = null;

        $product = $this->createProduct()
            ->setPriceTaxExcluded('20.000000')
            ->setPriceTaxIncluded('24.000000')
            ->setQuantity(4);
        $cartManager = new CartManager();
        $cart = $cartManager->createCart(token: 'guest-checkout-token');
        $cartManager->addProduct($cart, $product, 1);

        $order = (new OrderManager())->createGuestFromCart(
            cart: $cart,
            shippingAddress: $address,
            shippingAmountTaxIncludedCents: (new ShippingRateCalculator())->amountForSubtotal(
                $cart->getTotalTaxIncludedCents(),
            ),
            orderNumber: 'UP-TEST-GUEST',
        );

        self::assertSame(CartStatus::CONVERTED, $cart->getStatus());
        self::assertNull($order->getUser());
        self::assertNull($order->getCustomerEmail());
        self::assertSame('Client Invite', $order->getCustomerName());
        self::assertSame('20 rue de Lyon', $order->getShippingStreet());
        self::assertSame(475, $order->getShippingAmountTaxIncludedCents());
        self::assertSame(2875, $order->getTotalTaxIncludedCents());
        self::assertSame(PaymentStatus::PENDING, $order->getPaymentStatus());
    }

    private function createProduct(): Product
    {
        return (new Product())
            ->setName('Figurine Collector Arcane')
            ->setReference('ULTRA-001')
            ->setEan('0123456789012')
            ->setCategory((new Category())->setName('Figurines'))
            ->setLicense((new License())->setName('Arcane'))
            ->setPriceTaxExcluded('10.000000')
            ->setPriceTaxIncluded('12.000000');
    }
}
