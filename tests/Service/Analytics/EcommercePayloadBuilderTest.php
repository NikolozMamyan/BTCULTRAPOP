<?php

namespace App\Tests\Service\Analytics;

use App\Entity\Cart;
use App\Entity\Category;
use App\Entity\License;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Product;
use App\Entity\PromoCode;
use App\Enum\PromoDiscountType;
use App\Service\Analytics\EcommercePayloadBuilder;
use App\Service\CartManager;
use App\Service\PromoCodeManager;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class EcommercePayloadBuilderTest extends TestCase
{
    public function testItBuildsProductViewItemPayload(): void
    {
        $product = $this->product()
            ->setPromoPriceTaxIncluded('9.990000');

        $payload = $this->builder()->viewItem($product);

        self::assertSame('EUR', $payload['currency']);
        self::assertSame(9.99, $payload['value']);
        self::assertCount(1, $payload['items']);
        self::assertSame([
            'item_id' => 'ULTRA-001',
            'item_name' => 'ULTRAPOP Test Product',
            'item_brand' => 'ULTRAPOP',
            'item_category' => 'Boissons',
            'item_category2' => 'Naruto',
            'price' => 9.99,
            'quantity' => 1,
        ], $payload['items'][0]);
    }

    public function testItBuildsCartPayloadWithCouponAndNumericAmounts(): void
    {
        $cart = $this->cartWithProducts();
        $cart->setPromoCode((new PromoCode())
            ->setCode('SAVE10')
            ->setDiscountType(PromoDiscountType::PERCENTAGE)
            ->setValue(10));

        $payload = $this->builder()->cart($cart);

        self::assertNotNull($payload);
        self::assertSame('EUR', $payload['currency']);
        self::assertSame(21.6, $payload['value']);
        self::assertSame('SAVE10', $payload['coupon']);
        self::assertSame('ULTRA-001', $payload['items'][0]['item_id']);
        self::assertSame(12.0, $payload['items'][0]['price']);
        self::assertSame(2, $payload['items'][0]['quantity']);
    }

    public function testItBuildsPurchasePayloadOnlyForPaidOrders(): void
    {
        $order = $this->orderWithSnapshotItem()
            ->setOrderNumber('UP-2026-0001')
            ->setShippingAmountTaxIncludedCents(490);
        $order->refreshTotals();

        self::assertNull($this->builder()->purchase($order));

        $order->markPaid(new \DateTimeImmutable('2026-07-06 10:00:00'));

        $payload = $this->builder()->purchase($order);

        self::assertNotNull($payload);
        self::assertSame('UP-2026-0001', $payload['transaction_id']);
        self::assertSame(24.0, $payload['value']);
        self::assertSame(4.9, $payload['shipping']);
        self::assertSame('EUR', $payload['currency']);
        self::assertSame('ULTRA-ORDER-001', $payload['items'][0]['item_id']);
        self::assertSame('ULTRAPOP Order Product', $payload['items'][0]['item_name']);
        self::assertSame(12.0, $payload['items'][0]['price']);
        self::assertSame(2, $payload['items'][0]['quantity']);
    }

    public function testItBuildsPurchasePayloadWithDiscountSnapshot(): void
    {
        $order = $this->orderWithSnapshotItem()
            ->setOrderNumber('UP-2026-0002')
            ->setShippingAmountTaxIncludedCents(490)
            ->setDiscountAmountTaxIncludedCents(400)
            ->setPromoCode((new PromoCode())->setCode('ORDER400'));
        $order->refreshTotals();
        $order->markPaid();

        $payload = $this->builder()->purchase($order);

        self::assertNotNull($payload);
        self::assertSame(20.0, $payload['value']);
        self::assertSame(4.9, $payload['shipping']);
        self::assertSame('ORDER400', $payload['coupon']);
    }

    public function testProductIdentifierFallsBackToInternalProductId(): void
    {
        $product = $this->product(reference: '', ean: null);
        $this->setEntityId($product, 987);

        $payload = $this->builder()->viewItem($product);

        self::assertSame('987', $payload['items'][0]['item_id']);
    }

    private function builder(): EcommercePayloadBuilder
    {
        return new EcommercePayloadBuilder(new PromoCodeManager($this->createStub(EntityManagerInterface::class)));
    }

    private function cartWithProducts(): Cart
    {
        $cartManager = new CartManager();
        $cart = $cartManager->createCart(token: 'analytics-cart');
        $cartManager->addProduct($cart, $this->product(), 2);

        return $cart;
    }

    private function product(string $reference = 'ULTRA-001', ?string $ean = '3770015056008'): Product
    {
        return (new Product())
            ->setName('ULTRAPOP Test Product')
            ->setReference($reference)
            ->setEan($ean)
            ->setCategory((new Category())->setName('Boissons'))
            ->setLicense((new License())->setName('Naruto'))
            ->setPriceTaxExcluded('10.000000')
            ->setPriceTaxIncluded('12.000000')
            ->setTaxRate('20');
    }

    private function orderWithSnapshotItem(): Order
    {
        $order = (new Order())
            ->setCurrency('EUR');
        $order->addItem((new OrderItem())
            ->setProductName('ULTRAPOP Order Product')
            ->setProductReference('ULTRA-ORDER-001')
            ->setProductEan('3770015056008')
            ->setCategoryName('Boissons')
            ->setLicenseName('Naruto')
            ->setQuantity(2)
            ->setUnitPriceTaxExcludedCents(1000)
            ->setUnitPriceTaxIncludedCents(1200)
            ->setTaxRate('20'));

        return $order;
    }

    private function setEntityId(object $entity, int $id): void
    {
        $property = new \ReflectionProperty($entity, 'id');
        $property->setValue($entity, $id);
    }
}
