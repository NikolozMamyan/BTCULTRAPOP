<?php

namespace App\Tests\Service;

use App\Service\HomeProductSelection;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class HomeProductSelectionTest extends KernelTestCase
{
    public function testProductsInRecentActiveCartsAreSelectedFirst(): void
    {
        self::bootKernel();
        $connection = $this->databaseConnectionOrSkip();
        $productId = $connection->fetchOne(
            'SELECT p.id
             FROM product p
             INNER JOIN category c ON c.id = p.category_id
             INNER JOIN product_license l ON l.id = p.license_id
             WHERE p.active = true
               AND p.quantity > 0
               AND c.active = true
               AND l.active = true
             ORDER BY p.id DESC
             LIMIT 1',
        );

        if (false === $productId) {
            self::markTestSkipped('No active storefront product is available in the test database.');
        }

        $connection->beginTransaction();

        try {
            $now = new \DateTimeImmutable();

            for ($index = 0; $index < 12; ++$index) {
                $connection->insert('cart', [
                    'user_id' => null,
                    'token' => sprintf('home-selection-%s-%02d', bin2hex(random_bytes(8)), $index),
                    'status' => 'active',
                    'expires_at' => $now->modify('+1 day')->format('Y-m-d H:i:s'),
                    'created_at' => $now->format('Y-m-d H:i:s'),
                    'updated_at' => $now->format('Y-m-d H:i:s'),
                ]);
                $cartId = (int) $connection->lastInsertId();

                $connection->insert('cart_item', [
                    'cart_id' => $cartId,
                    'product_id' => (int) $productId,
                    'quantity' => 1,
                    'unit_price_tax_excluded_cents' => 100,
                    'unit_price_tax_included_cents' => 120,
                    'created_at' => $now->format('Y-m-d H:i:s'),
                    'updated_at' => $now->format('Y-m-d H:i:s'),
                ]);
            }

            $selection = static::getContainer()->get(HomeProductSelection::class);
            \assert($selection instanceof HomeProductSelection);
            $products = $selection->products(limit: 4);

            self::assertCount(4, $products);
            self::assertSame((int) $productId, $products[0]['id']);
        } finally {
            $connection->rollBack();
        }
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
