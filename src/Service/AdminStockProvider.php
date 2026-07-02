<?php

namespace App\Service;

use App\Enum\StockSource;
use App\Entity\Product;
use App\Repository\ProductRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

final readonly class AdminStockProvider
{
    public function __construct(
        private ProductRepository $products,
        private Connection $connection,
    )
    {
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function sources(): array
    {
        return array_map(
            static fn (StockSource $source): array => [
                'value' => $source->value,
                'label' => $source->labelKey(),
            ],
            StockSource::cases(),
        );
    }

    /**
     * @return list<array{id: int, reference: string, name: string, quantity: int|null}>
     */
    public function products(StockSource $source): array
    {
        $products = $this->products->findForStockAdmin();
        $quantities = $this->quantities($source, $products);

        return array_map(
            static function (Product $product) use ($source, $quantities): array {
                $productId = (int) $product->getId();

                return [
                    'id' => $productId,
                    'reference' => $product->getReference(),
                    'name' => $product->getName(),
                    'quantity' => $quantities[$productId] ?? (StockSource::BUREAU === $source ? $product->getQuantity() : null),
                ];
            },
            $products,
        );
    }

    /**
     * @param list<Product> $products
     *
     * @return array<int, int|null>
     */
    private function quantities(StockSource $source, array $products): array
    {
        $productIds = array_values(array_filter(
            array_map(static fn (Product $product): ?int => $product->getId(), $products),
        ));

        if ([] === $productIds) {
            return [];
        }

        try {
            $rows = $this->connection->executeQuery(
                sprintf('SELECT product_id, quantity FROM %s WHERE product_id IN (?)', $source->tableName()),
                [$productIds],
                [ArrayParameterType::INTEGER],
            )->fetchAllAssociative();
        } catch (\Throwable) {
            return [];
        }

        $quantities = [];

        foreach ($rows as $row) {
            $quantities[(int) $row['product_id']] = null === $row['quantity'] ? null : (int) $row['quantity'];
        }

        return $quantities;
    }
}
