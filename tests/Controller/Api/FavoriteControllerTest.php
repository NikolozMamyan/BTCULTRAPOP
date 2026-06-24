<?php

namespace App\Tests\Controller\Api;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class FavoriteControllerTest extends WebTestCase
{
    public function testFavoriteCanBeToggledForAuthenticatedUser(): void
    {
        $client = static::createClient();
        $this->skipIfDatabaseIsUnavailable();
        $this->skipIfStorefrontCatalogIsUnavailable();
        $connection = static::getContainer()->get(Connection::class);
        \assert($connection instanceof Connection);

        $email = sprintf('favorite-%s@example.com', bin2hex(random_bytes(4)));

        try {
            $profile = $client->request('GET', '/profil');
            $registerToken = $profile->filter('form[action="/auth/register"][method="post"] input[name="_csrf_token"]')->attr('value');

            $client->request('POST', '/auth/register', [
                '_csrf_token' => $registerToken,
                'last_name' => 'Favorite',
                'first_name' => 'Tester',
                'email' => $email,
                'password' => 'password-secure',
                'address_name' => '',
                'street' => '10 rue Test',
                'postal_code' => '75001',
                'city' => 'Paris',
                'accept_terms' => '1',
            ]);

            self::assertResponseRedirects('/profil');

            $shop = $client->request('GET', '/boutique');
            $csrfToken = $shop->filter('#storefront-app')->attr('data-favorites-csrf-value');

            $client->request(
                'POST',
                '/api/favorites/1648',
                server: [
                    'CONTENT_TYPE' => 'application/json',
                    'HTTP_X_CSRF_TOKEN' => $csrfToken,
                ],
                content: '{}',
            );

            self::assertResponseIsSuccessful();
            $payload = $this->jsonResponse($client->getResponse()->getContent());
            self::assertTrue($payload['favorite']);
            self::assertSame(1, $payload['count']);

            $client->request('GET', '/favoris');
            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('.shop-product-card:first-child', 'ULTRA ICE TEA - Vegeta');
            self::assertSelectorExists('.shop-product-card .favorite-button.is-active');

            $client->request(
                'POST',
                '/api/favorites/1648',
                server: [
                    'CONTENT_TYPE' => 'application/json',
                    'HTTP_X_CSRF_TOKEN' => $csrfToken,
                ],
                content: '{}',
            );

            self::assertResponseIsSuccessful();
            $payload = $this->jsonResponse($client->getResponse()->getContent());
            self::assertFalse($payload['favorite']);
            self::assertSame(0, $payload['count']);
        } finally {
            $connection->delete('app_user', ['email' => $email]);
        }
    }

    public function testFavoriteRequiresAuthentication(): void
    {
        $client = static::createClient();
        $this->skipIfDatabaseIsUnavailable();
        $this->skipIfStorefrontCatalogIsUnavailable();
        $crawler = $client->request('GET', '/boutique');
        $csrfToken = $crawler->filter('#storefront-app')->attr('data-favorites-csrf-value');

        $client->request(
            'POST',
            '/api/favorites/1648',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_CSRF_TOKEN' => $csrfToken,
            ],
            content: '{}',
        );

        self::assertResponseStatusCodeSame(401);
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonResponse(string $content): array
    {
        return json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
    }

    private function skipIfDatabaseIsUnavailable(): void
    {
        try {
            $connection = static::getContainer()->get(Connection::class);
            \assert($connection instanceof Connection);
            $connection->executeQuery('SELECT 1');
        } catch (\Throwable $exception) {
            self::markTestSkipped(sprintf('Database connection is unavailable in test env: %s', $exception->getMessage()));
        }
    }

    private function skipIfStorefrontCatalogIsUnavailable(): void
    {
        $connection = static::getContainer()->get(Connection::class);
        \assert($connection instanceof Connection);

        if ((int) $connection->fetchOne("SELECT COUNT(*) FROM product WHERE reference = '28989'") < 1) {
            self::markTestSkipped('Storefront product catalog is not loaded in the test database.');
        }
    }
}
