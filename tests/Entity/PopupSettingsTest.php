<?php

namespace App\Tests\Entity;

use App\Entity\PopupSettings;
use App\Entity\PromoCode;
use App\Entity\User;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;

final class PopupSettingsTest extends TestCase
{
    public function testActivePopupRequiresAPublicPromoCode(): void
    {
        $validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();
        $settings = (new PopupSettings())->setActive(true);

        $violations = $validator->validate($settings);

        self::assertSame('promoCode', $violations[0]->getPropertyPath());
        self::assertSame('admin.popup.error.code_required', $violations[0]->getMessage());

        $settings->setPromoCode((new PromoCode())->setAssignedUser((new User())->setEmail('private@example.com')));
        $violations = $validator->validate($settings);

        self::assertSame('promoCode', $violations[0]->getPropertyPath());
        self::assertSame('admin.popup.error.public_code_required', $violations[0]->getMessage());
    }

    public function testItNormalizesEditableContent(): void
    {
        $settings = (new PopupSettings())
            ->setTitle('  Offre du jour  ')
            ->setMessage('  Copiez votre code.  ');

        self::assertSame('Offre du jour', $settings->getTitle());
        self::assertSame('Copiez votre code.', $settings->getMessage());
        self::assertFalse($settings->isActive());
    }
}
