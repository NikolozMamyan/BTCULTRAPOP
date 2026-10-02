<?php

namespace App\Service;

use App\Enum\StockSource;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class AdminStockSynchronizer
{
    private const SAGE_DEPOT = 'Salle Echantillon';

    public function __construct(
        private ProductRepository $products,
        private SageApiClient $sageApi,
        private ProductStockSourceWriter $stockSourceWriter,
        private StockSettingsManager $stockSettingsManager,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return array{updated: int, missing: int}
     */
    public function synchronizeBureau(): array
    {
        $sageStock = $this->stockByReference($this->sageApi->stock(self::SAGE_DEPOT));
        $activeSource = $this->stockSettingsManager->activeSource();

        return $this->entityManager->wrapInTransaction(function () use ($sageStock, $activeSource): array {
            $updated = 0;
            $missing = 0;

            foreach ($this->products->findForStockAdmin() as $product) {
                $reference = $this->normalizeReference($product->getReference());

                if (!array_key_exists($reference, $sageStock)) {
                    ++$missing;

                    continue;
                }

                $quantity = $sageStock[$reference];
                $this->stockSourceWriter->write($product, StockSource::BUREAU, $quantity);

                if (StockSource::BUREAU === $activeSource) {
                    $product->setQuantity($quantity);
                }

                ++$updated;
            }

            return [
                'updated' => $updated,
                'missing' => $missing,
            ];
        });
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return array<string, int>
     */
    private function stockByReference(array $rows): array
    {
        $stock = [];

        foreach ($rows as $row) {
            $reference = $row['reference'] ?? null;
            $quantity = $row['stockDispo'] ?? null;

            if ((!is_string($reference) && !is_int($reference)) || !is_numeric($quantity)) {
                continue;
            }

            $reference = $this->normalizeReference((string) $reference);

            if ('' === $reference) {
                continue;
            }

            $stock[$reference] = max(0, (int) $quantity);
        }

        return $stock;
    }

    private function normalizeReference(string $reference): string
    {
        return mb_strtoupper(trim($reference));
    }
}
