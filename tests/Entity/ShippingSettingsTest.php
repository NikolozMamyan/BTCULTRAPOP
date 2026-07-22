<?php

namespace App\Tests\Entity;

use App\Entity\ShippingSettings;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;

final class ShippingSettingsTest extends TestCase
{
    public function testDefaultShippingConfigurationIsValid(): void
    {
        $settings = new ShippingSettings();
        $violations = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator()
            ->validate($settings);

        self::assertCount(0, $violations);
        self::assertSame(1000, $settings->getMinimumOrderCents());
        self::assertCount(5, $settings->getTiers());
        self::assertSame(600, $settings->getSortedTiers()[0]->getShippingAmountCents());
        self::assertSame(0, $settings->getSortedTiers()[4]->getShippingAmountCents());
    }

    public function testFirstTierMustMatchMinimumOrder(): void
    {
        $settings = (new ShippingSettings())->setMinimumOrderCents(1200);
        $violations = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator()
            ->validate($settings);

        self::assertGreaterThan(0, $violations->count());
        self::assertSame('admin.shipping.error.first_threshold', $violations[0]->getMessage());
    }
}
