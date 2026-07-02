<?php

namespace App\Service;

use App\Entity\Product;
use App\Enum\StockSource;
use Doctrine\ORM\EntityManagerInterface;

final readonly class AdminStockManager
{
    private const MAX_QUANTITY = 2_147_483_647;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private ProductStockSourceWriter $stockSourceWriter,
        private StockSettingsManager $stockSettingsManager,
    )
    {
    }

    /**
     * @return array{id: int, source: string, quantity: int|null}
     */
    public function updateProduct(Product $product, string $quantity, string $sourceValue): array
    {
        $source = StockSource::tryFrom(trim($sourceValue));

        if (!$source instanceof StockSource) {
            throw new \InvalidArgumentException('admin.stock.error.invalid_source');
        }

        $normalizedQuantity = $this->normalizeQuantity(trim($quantity), $source);

        $this->stockSourceWriter->write($product, $source, $normalizedQuantity);

        if ($source === $this->stockSettingsManager->activeSource()) {
            $product->setQuantity(max(0, (int) $normalizedQuantity));
            $this->entityManager->flush();
        }

        return [
            'id' => (int) $product->getId(),
            'source' => $source->value,
            'quantity' => $normalizedQuantity,
        ];
    }

    private function normalizeQuantity(string $quantity, StockSource $source): ?int
    {
        if ('' === $quantity && $source->allowsEmptyQuantity()) {
            return null;
        }

        if (!preg_match('/^\d+$/', $quantity) || (float) $quantity > self::MAX_QUANTITY) {
            throw new \InvalidArgumentException('admin.stock.error.invalid_quantity');
        }

        return (int) $quantity;
    }
}
