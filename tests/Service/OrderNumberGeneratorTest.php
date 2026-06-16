<?php

namespace App\Tests\Service;

use App\Service\OrderNumberGenerator;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class OrderNumberGeneratorTest extends KernelTestCase
{
    public function testOrderNumbersAreSequentialForTheSameDay(): void
    {
        self::bootKernel();
        $connection = static::getContainer()->get(Connection::class);
        \assert($connection instanceof Connection);

        try {
            $connection->executeQuery('SELECT 1');
        } catch (\Throwable $exception) {
            self::markTestSkipped(sprintf('Database connection is unavailable in test env: %s', $exception->getMessage()));
        }

        $connection->delete('order_sequence', ['date_key' => '20990101']);

        try {
            $generator = new OrderNumberGenerator($connection);

            self::assertSame('UP-20990101-000001', $generator->generate(new \DateTimeImmutable('2099-01-01 10:00:00')));
            self::assertSame('UP-20990101-000002', $generator->generate(new \DateTimeImmutable('2099-01-01 11:00:00')));
        } finally {
            $connection->delete('order_sequence', ['date_key' => '20990101']);
        }
    }
}
