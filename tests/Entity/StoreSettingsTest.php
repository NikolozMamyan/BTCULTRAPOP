<?php

namespace App\Tests\Entity;

use App\Entity\StoreSettings;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validation;

final class StoreSettingsTest extends TestCase
{
    public function testDisabledMaintenanceDoesNotRequireDates(): void
    {
        $violations = $this->validate(new StoreSettings());

        self::assertCount(0, $violations);
    }

    public function testEnabledMaintenanceRequiresBothDisplayDates(): void
    {
        $violations = $this->validate((new StoreSettings())->setMaintenanceEnabled(true));

        self::assertCount(2, $violations);
        self::assertSame('maintenanceStartsAt', $violations[0]->getPropertyPath());
        self::assertSame('maintenanceEndsAt', $violations[1]->getPropertyPath());
    }

    public function testDisplayEndMustBeLaterThanDisplayStart(): void
    {
        $settings = (new StoreSettings())
            ->setMaintenanceEnabled(true)
            ->setMaintenanceStartsAt(new \DateTimeImmutable('2026-08-18 10:00:00'))
            ->setMaintenanceEndsAt(new \DateTimeImmutable('2026-08-18 09:00:00'));

        $violations = $this->validate($settings);

        self::assertCount(1, $violations);
        self::assertSame('maintenanceEndsAt', $violations[0]->getPropertyPath());
        self::assertSame('admin.store_settings.error.invalid_dates', $violations[0]->getMessage());
    }

    public function testValidDisplayDatesDoNotControlTheSwitch(): void
    {
        $settings = (new StoreSettings())
            ->setMaintenanceStartsAt(new \DateTimeImmutable('2026-08-18 10:00:00'))
            ->setMaintenanceEndsAt(new \DateTimeImmutable('2026-08-20 18:00:00'));

        self::assertFalse($settings->isMaintenanceEnabled());
        self::assertCount(0, $this->validate($settings));

        $settings->setMaintenanceEnabled(true);

        self::assertTrue($settings->isMaintenanceEnabled());
        self::assertCount(0, $this->validate($settings));
    }

    private function validate(StoreSettings $settings): ConstraintViolationListInterface
    {
        return Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator()
            ->validate($settings);
    }
}
