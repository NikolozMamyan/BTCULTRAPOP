<?php

namespace App\Tests\Controller\Api;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CartControllerTest extends WebTestCase
{
    public function testCartItemsCanBeManagedWithJsonRequests(): void
    {
        $client = static::createClient();
        $this->skipIfDatabaseIsUnavailable();
        $crawler = $client->request('GET', '/boutique');
        $csrfToken = $crawler->filter('#storefront-app')->attr('data-cart-csrf-value');

        $client->request(
            'POST',
            '/api/cart/items',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_CSRF_TOKEN' => $csrfToken,
            ],
            content: json_encode(['productId' => 1, 'quantity' => 2], \JSON_THROW_ON_ERROR),
        );

        self::assertResponseIsSuccessful();
        $payload = $this->jsonResponse($client->getResponse()->getContent());
        self::assertSame(2, $payload['cart']['totalQuantity']);
        self::assertSame('119,80 €', $payload['cart']['subtotalFormatted']);
        self::assertSame('Figurine Collector Arcane', $payload['cart']['items'][0]['name']);
        self::assertNotNull($client->getCookieJar()->get('ultrapop_cart'));

        $updateUrl = $payload['cart']['items'][0]['updateUrl'];

        $client->request(
            'PATCH',
            $updateUrl,
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_CSRF_TOKEN' => $csrfToken,
            ],
            content: json_encode(['quantity' => 3], \JSON_THROW_ON_ERROR),
        );

        self::assertResponseIsSuccessful();
        $payload = $this->jsonResponse($client->getResponse()->getContent());
        self::assertSame(3, $payload['cart']['totalQuantity']);
        self::assertSame('179,70 €', $payload['cart']['totalFormatted']);

        $removeUrl = $payload['cart']['items'][0]['removeUrl'];

        $client->request(
            'DELETE',
            $removeUrl,
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_CSRF_TOKEN' => $csrfToken,
            ],
        );

        self::assertResponseIsSuccessful();
        $payload = $this->jsonResponse($client->getResponse()->getContent());
        self::assertTrue($payload['cart']['empty']);
        self::assertSame(0, $payload['cart']['totalQuantity']);
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
}
