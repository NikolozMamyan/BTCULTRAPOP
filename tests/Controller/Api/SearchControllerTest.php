<?php

namespace App\Tests\Controller\Api;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SearchControllerTest extends WebTestCase
{
    public function testProductSearchReturnsMatchingProducts(): void
    {
        $client = static::createClient();
        $connection = $this->databaseConnectionOrSkip();
        $productName = $connection->fetchOne(
            'SELECT p.name
             FROM product p
             INNER JOIN category c ON c.id = p.category_id
             INNER JOIN license l ON l.id = p.license_id
             WHERE p.active = true AND c.active = true AND l.active = true
             ORDER BY p.id
             LIMIT 1',
        );

        if (!\is_string($productName) || mb_strlen(trim($productName)) < 2) {
            self::markTestSkipped('No searchable storefront product is available in the test database.');
        }

        $query = mb_substr(trim($productName), 0, 2);
        $client->request('GET', '/api/search/products?q=' . urlencode($query));

        self::assertResponseIsSuccessful();

        $payload = json_decode($client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame($query, $payload['query']);
        self::assertNotEmpty($payload['results']);
        self::assertLessThanOrEqual(8, \count($payload['results']));
    }

    public function testProductSearchIgnoresQueriesShorterThanTwoCharacters(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/search/products?q=a');

        self::assertResponseIsSuccessful();
        self::assertSame(
            ['query' => 'a', 'results' => []],
            json_decode($client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR),
        );
    }

    private function databaseConnectionOrSkip(): Connection
    {
        try {
            $connection = static::getContainer()->get(Connection::class);
            \assert($connection instanceof Connection);
            $connection->executeQuery('SELECT 1');

            return $connection;
        } catch (\Throwable $exception) {
            self::markTestSkipped(sprintf('Database connection is unavailable in test env: %s', $exception->getMessage()));
        }
    }
}
