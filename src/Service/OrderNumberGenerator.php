<?php

namespace App\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

final readonly class OrderNumberGenerator
{
    public function __construct(private Connection $connection)
    {
    }

    public function generate(?\DateTimeImmutable $now = null): string
    {
        $dateKey = ($now ?? new \DateTimeImmutable())->format('Ymd');

        return $this->connection->transactional(function () use ($dateKey): string {
            try {
                $this->connection->insert('order_sequence', [
                    'date_key' => $dateKey,
                    'next_number' => 2,
                ]);

                $sequence = 1;
            } catch (UniqueConstraintViolationException) {
                $sequence = (int) $this->connection->fetchOne(
                    'SELECT next_number FROM order_sequence WHERE date_key = ? FOR UPDATE',
                    [$dateKey],
                );
                $this->connection->update('order_sequence', [
                    'next_number' => $sequence + 1,
                ], [
                    'date_key' => $dateKey,
                ]);
            }

            return sprintf('UP-%s-%06d', $dateKey, $sequence);
        });
    }
}
