<?php

namespace App\Service;

use App\Entity\Product;
use App\Enum\StockSource;
use Doctrine\DBAL\Connection;

final readonly class ProductStockSourceWriter
{
    public function __construct(private Connection $connection)
    {
    }

    public function syncBureau(Product $product): void
    {
        $this->write($product, StockSource::BUREAU, $product->getQuantity());
    }

    public function write(Product $product, StockSource $source, ?int $quantity): void
    {
        if (null === $quantity && !$source->allowsEmptyQuantity()) {
            throw new \LogicException(sprintf('Stock source "%s" cannot be empty.', $source->value));
        }

        $this->connection->executeStatement(
            sprintf(
                'INSERT INTO %s (product_id, quantity, created_at, updated_at)
                VALUES (?, ?, NOW(), NOW())
                ON DUPLICATE KEY UPDATE quantity = VALUES(quantity), updated_at = VALUES(updated_at)',
                $source->tableName(),
            ),
            [
                (int) $product->getId(),
                $quantity,
            ],
        );
    }
}
