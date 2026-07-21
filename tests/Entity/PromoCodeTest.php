<?php

namespace App\Tests\Entity;

use App\Entity\PromoCode;
use App\Entity\User;
use App\Enum\PromoApplicationType;
use App\Enum\PromoDiscountType;
use PHPUnit\Framework\TestCase;

final class PromoCodeTest extends TestCase
{
    public function testItNormalizesCodeAndCalculatesPercentageDiscount(): void
    {
        $promoCode = (new PromoCode())
            ->setCode(' welcome-10 ')
            ->setDiscountType(PromoDiscountType::PERCENTAGE)
            ->setValue('10');

        self::assertSame('WELCOME-10', $promoCode->getCode());
        self::assertSame('10.00', $promoCode->getValue());
        self::assertSame(1200, $promoCode->calculateDiscountCents(12000));
    }

    public function testItCalculatesFixedDiscountWithoutMakingProductsFree(): void
    {
        $promoCode = (new PromoCode())
            ->setDiscountType(PromoDiscountType::FIXED)
            ->setValue('20');

        self::assertSame(950, $promoCode->calculateDiscountCents(1000));
        self::assertSame(0, $promoCode->calculateDiscountCents(50));
    }

    public function testItCanDiscountAllOrPartOfShipping(): void
    {
        $percentageCode = (new PromoCode())
            ->setApplicationType(PromoApplicationType::SHIPPING)
            ->setDiscountType(PromoDiscountType::PERCENTAGE)
            ->setValue(50);
        $fixedCode = (new PromoCode())
            ->setApplicationType(PromoApplicationType::SHIPPING)
            ->setDiscountType(PromoDiscountType::FIXED)
            ->setValue(10);

        self::assertSame(300, $percentageCode->calculateDiscountCents(600));
        self::assertSame(600, $fixedCode->calculateDiscountCents(600));
        self::assertSame(0, $fixedCode->calculateDiscountCents(0));
    }

    public function testItChecksValidityUsageAndUserAssignment(): void
    {
        $assignedUser = (new User())->setEmail('assigned@example.com');
        $otherUser = (new User())->setEmail('other@example.com');
        $promoCode = (new PromoCode())
            ->setAssignedUser($assignedUser)
            ->setValidFrom(new \DateTimeImmutable('2026-06-01'))
            ->setValidUntil(new \DateTimeImmutable('2026-06-30'))
            ->setMaxUses(1);

        self::assertTrue($promoCode->isAvailableFor($assignedUser, new \DateTimeImmutable('2026-06-24')));
        self::assertFalse($promoCode->isAvailableFor($otherUser, new \DateTimeImmutable('2026-06-24')));
        self::assertFalse($promoCode->isAvailableFor(null, new \DateTimeImmutable('2026-06-24')));
        self::assertFalse($promoCode->isAvailableFor($assignedUser, new \DateTimeImmutable('2026-07-01')));
    }
}
