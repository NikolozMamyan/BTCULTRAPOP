<?php

namespace App\Tests\Service;

use App\Entity\Cart;
use App\Entity\CartItem;
use App\Entity\Product;
use App\Entity\PromoCode;
use App\Entity\User;
use App\Enum\PromoApplicationType;
use App\Enum\PromoDiscountType;
use App\Service\PromoCodeManager;
use App\Service\ShippingRateCalculator;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class PromoCodeManagerTest extends TestCase
{
    public function testItAppliesAndRemovesAValidCodeOnTaxExcludedProductAmounts(): void
    {
        $cart = $this->cartWithItem(5000, 6000, '20');
        $promoCode = (new PromoCode())
            ->setCode('TEST10')
            ->setDiscountType(PromoDiscountType::PERCENTAGE)
            ->setValue(10);

        $manager = $this->manager();

        self::assertSame(600, $manager->apply($cart, $promoCode, null));
        self::assertSame([
            'taxExcludedCents' => 500,
            'taxIncludedCents' => 600,
        ], $manager->discountAmountsForCart($cart));

        $manager->remove($cart);

        self::assertNull($cart->getPromoCode());
    }

    public function testItRejectsACodeAssignedToAnotherUser(): void
    {
        $promoCode = (new PromoCode())
            ->setAssignedUser((new User())->setEmail('assigned@example.com'));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('promo.flash.not_assigned');

        $this->manager()->apply(new Cart(), $promoCode, (new User())->setEmail('other@example.com'));
    }

    public function testItCalculatesShippingDiscountFromTheTaxExcludedShippingTier(): void
    {
        $cart = $this->cartWithItem(1000, 1200, '20');
        $promoCode = (new PromoCode())
            ->setApplicationType(PromoApplicationType::SHIPPING)
            ->setDiscountType(PromoDiscountType::PERCENTAGE)
            ->setValue(50);

        self::assertSame(360, $this->manager()->apply($cart, $promoCode, null));
        self::assertSame([
            'taxExcludedCents' => 300,
            'taxIncludedCents' => 360,
        ], $this->manager()->discountAmountsForCart($cart));
    }

    public function testItAppliesProductDiscountAcrossMixedVatRates(): void
    {
        $cart = new Cart();
        $cart->addItem($this->item(213, 256, '20'));
        $cart->addItem($this->item(372, 392, '5.5'));
        $promoCode = (new PromoCode())
            ->setCode('MIXED10')
            ->setDiscountType(PromoDiscountType::PERCENTAGE)
            ->setValue(10);

        $manager = $this->manager();
        $manager->apply($cart, $promoCode, null);

        self::assertSame([
            'taxExcludedCents' => 59,
            'taxIncludedCents' => 65,
        ], $manager->discountAmountsForCart($cart));
    }

    public function testItRejectsShippingCodeWhenDeliveryIsAlreadyFree(): void
    {
        $cart = $this->cartWithItem(5000, 6000, '20');
        $promoCode = (new PromoCode())
            ->setApplicationType(PromoApplicationType::SHIPPING)
            ->setDiscountType(PromoDiscountType::PERCENTAGE)
            ->setValue(100);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('promo.flash.shipping_already_free');

        $this->manager()->apply($cart, $promoCode, null);
    }

    private function manager(): PromoCodeManager
    {
        return new PromoCodeManager(
            $this->createStub(EntityManagerInterface::class),
            new ShippingRateCalculator(),
        );
    }

    private function cartWithItem(int $taxExcludedCents, int $taxIncludedCents, string $taxRate): Cart
    {
        $cart = new Cart();
        $cart->addItem($this->item($taxExcludedCents, $taxIncludedCents, $taxRate));

        return $cart;
    }

    private function item(int $taxExcludedCents, int $taxIncludedCents, string $taxRate): CartItem
    {
        return (new CartItem())
            ->setProduct((new Product())->setTaxRate($taxRate))
            ->setUnitPriceTaxExcludedCents($taxExcludedCents)
            ->setUnitPriceTaxIncludedCents($taxIncludedCents);
    }
}
