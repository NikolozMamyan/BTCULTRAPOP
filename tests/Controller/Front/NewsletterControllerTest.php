<?php

namespace App\Tests\Controller\Front;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class NewsletterControllerTest extends WebTestCase
{
    public function testHomeAndFooterExposeWorkingNewsletterForms(): void
    {
        $client = static::createClient();
        $this->skipIfDatabaseIsUnavailable();

        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#newsletter .home-newsletter-card[style*="img/home/newsletter"]');
        self::assertSelectorExists('#newsletter form[action="/newsletter/inscription"][method="post"]');
        self::assertSelectorExists('#newsletter [data-controller="newsletter"] form[data-newsletter-target="form"][data-action="submit->newsletter#submit"]');
        self::assertSelectorExists('#newsletter [data-controller="newsletter"] [data-newsletter-target="message"]');
        self::assertSelectorExists('#newsletter input[name="email"][type="email"][required]');
        self::assertSelectorExists('#newsletter input[name="_csrf_token"]');
        self::assertSelectorExists('footer form[action="/newsletter/inscription"][method="post"]');
        self::assertSelectorExists('footer [data-controller="newsletter"] form[data-newsletter-target="form"][data-action="submit->newsletter#submit"]');
        self::assertSelectorExists('footer [data-controller="newsletter"] [data-newsletter-target="message"]');
        self::assertSelectorExists('footer input[name="email"][type="email"][required]');
        self::assertSelectorExists('footer input[name="_csrf_token"]');
        self::assertSelectorExists('footer a[href="https://www.instagram.com/ultrapop_/"][target="_blank"]');
        self::assertSelectorExists('footer a[href="https://www.tiktok.com/@ultrapop_"][target="_blank"]');
        self::assertSelectorExists('footer a[href="https://www.linkedin.com/company/ultrapopbylnstrade"][target="_blank"]');
        self::assertSelectorNotExists('footer .fa-x-twitter');
        self::assertSelectorNotExists('footer .fa-youtube');
    }

    public function testNewsletterRejectsAnInvalidEmail(): void
    {
        $client = static::createClient();
        $this->skipIfDatabaseIsUnavailable();

        $crawler = $client->request('GET', '/');
        $token = $crawler->filter('#newsletter input[name="_csrf_token"]')->attr('value');

        $client->request('POST', '/newsletter/inscription', [
            '_csrf_token' => $token,
            'email' => 'adresse-invalide',
            'source' => 'home',
            'redirect' => '/#newsletter',
        ]);

        self::assertResponseRedirects('/#newsletter');
        $client->followRedirect();
        self::assertSelectorTextContains('[role="alert"]', 'adresse e-mail valide');
    }

    public function testNewsletterReturnsValidationErrorsAsJsonForAjax(): void
    {
        $client = static::createClient();
        $this->skipIfDatabaseIsUnavailable();

        $crawler = $client->request('GET', '/');
        $token = $crawler->filter('#newsletter input[name="_csrf_token"]')->attr('value');

        $client->request('POST', '/newsletter/inscription', [
            '_csrf_token' => $token,
            'email' => 'adresse-invalide',
            'source' => 'home',
            'redirect' => '/#newsletter',
        ], [], [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('content-type', 'application/json');

        $payload = json_decode($client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        self::assertFalse($payload['success']);
        self::assertStringContainsString('adresse e-mail valide', $payload['message']);
    }

    public function testNewsletterPersistsSubscriberAndSendsWelcomeEmail(): void
    {
        $client = static::createClient();
        $this->skipIfDatabaseIsUnavailable();
        $connection = $this->connection();

        if (!$connection->createSchemaManager()->tablesExist(['newsletter_subscriber'])) {
            self::markTestSkipped('Run Doctrine migrations before testing newsletter persistence.');
        }

        $email = sprintf('newsletter-%s@example.com', bin2hex(random_bytes(4)));
        $crawler = $client->request('GET', '/');
        $token = $crawler->filter('#newsletter input[name="_csrf_token"]')->attr('value');

        try {
            $client->request('POST', '/newsletter/inscription', [
                '_csrf_token' => $token,
                'email' => $email,
                'source' => 'home',
                'redirect' => '/#newsletter',
            ]);

            self::assertResponseRedirects('/#newsletter');
            self::assertEmailCount(1);
            self::assertSame(1, $connection->fetchOne(
                'SELECT COUNT(*) FROM newsletter_subscriber WHERE email = :email AND active = 1',
                ['email' => $email],
            ));

            $message = self::getMailerMessage();
            self::assertNotNull($message);
            self::assertEmailHeaderSame($message, 'To', $email);
            self::assertEmailHeaderSame($message, 'Subject', 'Bienvenue dans la newsletter ULTRAPOP');
        } finally {
            $connection->delete('newsletter_subscriber', ['email' => $email]);
        }
    }

    private function skipIfDatabaseIsUnavailable(): void
    {
        try {
            $this->connection()->executeQuery('SELECT 1');
        } catch (\Throwable $exception) {
            self::markTestSkipped(sprintf('Database connection is unavailable in test env: %s', $exception->getMessage()));
        }
    }

    private function connection(): Connection
    {
        $connection = static::getContainer()->get(Connection::class);
        \assert($connection instanceof Connection);

        return $connection;
    }
}
