<?php

namespace App\Tests\Service;

use App\Entity\Cart;
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
    public function testItAppliesAndRemovesAValidCode(): void
    {
        $cart = $this->createMock(Cart::class);
        $cart->method('getTotalTaxIncludedCents')->willReturn(5000);
        $cart->expects(self::once())->method('setPromoCode');

        $promoCode = (new PromoCode())
            ->setCode('TEST10')
            ->setDiscountType(PromoDiscountType::PERCENTAGE)
            ->setValue(10);

        $manager = new PromoCodeManager(
            $this->createStub(EntityManagerInterface::class),
            new ShippingRateCalculator(),
        );

        self::assertSame(500, $manager->apply($cart, $promoCode, null));
    }

    public function testItRejectsACodeAssignedToAnotherUser(): void
    {
        $cart = new Cart();
        $promoCode = (new PromoCode())
            ->setAssignedUser((new User())->setEmail('assigned@example.com'));
        $manager = new PromoCodeManager(
            $this->createStub(EntityManagerInterface::class),
            new ShippingRateCalculator(),
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('promo.flash.not_assigned');

        $manager->apply($cart, $promoCode, (new User())->setEmail('other@example.com'));
    }

    public function testItCalculatesShippingDiscountFromTheCurrentShippingTier(): void
    {
        $cart = $this->createMock(Cart::class);
        $cart->method('getTotalTaxIncludedCents')->willReturn(1000);
        $cart->expects(self::once())->method('setPromoCode');
        $promoCode = (new PromoCode())
            ->setApplicationType(PromoApplicationType::SHIPPING)
            ->setDiscountType(PromoDiscountType::PERCENTAGE)
            ->setValue(50);
        $manager = new PromoCodeManager(
            $this->createStub(EntityManagerInterface::class),
            new ShippingRateCalculator(),
        );

        self::assertSame(300, $manager->apply($cart, $promoCode, null));
    }

    public function testItRejectsShippingCodeWhenDeliveryIsAlreadyFree(): void
    {
        $cart = $this->createStub(Cart::class);
        $cart->method('getTotalTaxIncludedCents')->willReturn(5000);
        $promoCode = (new PromoCode())
            ->setApplicationType(PromoApplicationType::SHIPPING)
            ->setDiscountType(PromoDiscountType::PERCENTAGE)
            ->setValue(100);
        $manager = new PromoCodeManager(
            $this->createStub(EntityManagerInterface::class),
            new ShippingRateCalculator(),
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('promo.flash.shipping_already_free');

        $manager->apply($cart, $promoCode, null);
    }
}
