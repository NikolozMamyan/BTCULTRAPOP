<?php

namespace App\Tests\Service;

use App\Entity\Address;
use App\Entity\User;
use App\Service\AdminEmailVariableRenderer;
use PHPUnit\Framework\TestCase;

final class AdminEmailVariableRendererTest extends TestCase
{
    public function testItPersonalizesAndEscapesCustomerVariables(): void
    {
        $user = (new User())
            ->setFirstName('<Camille>')
            ->setLastName('Dupont & Co')
            ->setEmail('camille@example.com')
            ->setPhone('0612345678')
            ->setLoyaltyPoints(125)
            ->setPreferredLocale('fr');
        $user->addAddress(
            (new Address())
                ->setName('Maison')
                ->setStreet('10 rue de Paris')
                ->setPostalCode('75001')
                ->setCity('Paris')
                ->setCountryCode('FR')
                ->setDefaultAddress(true),
        );

        $renderer = new AdminEmailVariableRenderer();
        $html = $renderer->renderHtml(
            '<p>{{ client.firstName }} — {{ client.address }} — {{ client.loyaltyPoints }}</p>',
            $user,
            $user->getEmail(),
        );

        self::assertStringContainsString('&lt;Camille&gt;', $html);
        self::assertStringContainsString('10 rue de Paris, 75001 Paris, FR', $html);
        self::assertStringContainsString('125', $html);
        self::assertSame(
            'Bonjour <Camille> (camille@example.com)',
            $renderer->renderText(
                'Bonjour {{ client.firstName }} ({{ client.email }})',
                $user,
                $user->getEmail(),
            ),
        );
    }

    public function testExternalEmailUsesSafeFallbacks(): void
    {
        $renderer = new AdminEmailVariableRenderer();

        self::assertSame(
            'Bonjour Client externe@example.com — externe@example.com — ',
            $renderer->renderText(
                'Bonjour {{ client.firstName }} {{ client.name }} — {{ client.email }} — {{ client.phone }}',
                null,
                'externe@example.com',
            ),
        );
    }

    public function testUnsupportedVariableIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('admin.emailing.flash.invalid_variable');

        (new AdminEmailVariableRenderer())->assertSupportedVariables('<p>{{ client.secret }}</p>');
    }
}
