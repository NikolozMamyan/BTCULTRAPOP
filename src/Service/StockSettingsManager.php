<?php

namespace App\Service;

use App\Entity\Product;
use App\Entity\StockSettings;
use App\Enum\StockSource;
use App\Repository\ProductRepository;
use App\Repository\StockSettingsRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

final readonly class StockSettingsManager
{
    public function __construct(
        private StockSettingsRepository $settings,
        private ProductRepository $products,
        private EntityManagerInterface $entityManager,
        private Connection $connection,
    ) {
    }

    public function getSettings(): StockSettings
    {
        $settings = $this->settings->findOneBy([], ['id' => 'ASC']);

        if ($settings instanceof StockSettings) {
            return $settings;
        }

        $settings = new StockSettings();
        $this->entityManager->persist($settings);
        $this->entityManager->flush();

        return $settings;
    }

    public function activeSource(): StockSource
    {
        $settings = $this->settings->findOneBy([], ['id' => 'ASC']);

        return $settings instanceof StockSettings ? $settings->getActiveStockSource() : StockSource::default();
    }

    public function setActiveSource(StockSource $source): StockSettings
    {
        $settings = $this->getSettings();
        $settings->setActiveSource($source->value);

        $this->syncProductsFromSource($source);
        $this->entityManager->flush();

        return $settings;
    }

    private function syncProductsFromSource(StockSource $source): void
    {
        $quantities = $this->sourceQuantities($source);

        foreach ($this->products->findForStockAdmin() as $product) {
            if (!$product instanceof Product) {
                continue;
            }

            $product->setQuantity(max(0, (int) ($quantities[(int) $product->getId()] ?? 0)));
        }
    }

    /**
     * @return array<int, int|null>
     */
    private function sourceQuantities(StockSource $source): array
    {
        $rows = $this->connection->executeQuery(
            sprintf('SELECT product_id, quantity FROM %s', $source->tableName()),
        )->fetchAllAssociative();

        $quantities = [];

        foreach ($rows as $row) {
            $quantities[(int) $row['product_id']] = null === $row['quantity'] ? null : (int) $row['quantity'];
        }

        return $quantities;
    }
}
