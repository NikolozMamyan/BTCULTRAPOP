<?php

namespace App\Tests\Controller\Front;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class InformationControllerTest extends WebTestCase
{
    #[DataProvider('provideInformationPages')]
    public function testInformationPageIsAccessibleAndLinkedFromItsNavigation(
        string $path,
        string $title,
    ): void {
        $client = static::createClient();
        $client->request('GET', $path);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', $title);
        self::assertSelectorExists(sprintf('link[rel="canonical"][href="https://ultrapop.com%s"]', $path));
        self::assertSelectorExists('.information-nav');
        self::assertSelectorExists('.information-nav a.is-active');
        self::assertSelectorExists('footer a[href="/conditions-generales-de-vente"]');
        self::assertSelectorExists('footer a[href="/mentions-legales"]');
        self::assertSelectorExists('footer a[href="/politique-de-confidentialite"]');
    }

    public static function provideInformationPages(): iterable
    {
        yield 'delivery' => ['/livraison', 'Livraison'];
        yield 'returns' => ['/retours-et-retractation', 'Retours et rétractation'];
        yield 'terms' => ['/conditions-generales-de-vente', 'Conditions générales de vente'];
        yield 'legal' => ['/mentions-legales', 'Mentions légales'];
        yield 'privacy' => ['/politique-de-confidentialite', 'Politique de confidentialité'];
    }

    public function testContactFormValidatesAndAcceptsACompleteMessage(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/contact');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form[action="/contact"][method="post"]');
        self::assertSelectorExists('input[name="contact[subject]"]');
        self::assertSelectorExists('input[name="contact[email]"]');
        self::assertSelectorExists('textarea[name="contact[message]"]');

        $token = $crawler->filter('input[name="contact[_token]"]')->attr('value');
        $client->request('POST', '/contact', [
            'contact' => [
                '_token' => $token,
                'subject' => '',
                'email' => 'adresse-invalide',
                'message' => 'court',
                'website' => '',
            ],
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.contact-form', 'Veuillez indiquer le sujet');
        self::assertSelectorTextContains('.contact-form', 'adresse e-mail valide');
        self::assertSelectorTextContains('.contact-form', 'au moins 10 caractères');

        $crawler = $client->request('GET', '/contact');
        $token = $crawler->filter('input[name="contact[_token]"]')->attr('value');
        $client->request('POST', '/contact', [
            'contact' => [
                '_token' => $token,
                'subject' => 'Question sur ma commande UP-20260624-001',
                'email' => 'client@example.com',
                'message' => 'Bonjour, pouvez-vous me confirmer le suivi de ma commande ?',
                'website' => '',
            ],
        ]);

        self::assertResponseRedirects('/contact');
        $client->followRedirect();
        self::assertSelectorExists('.information-flash--success');
    }
}
