<?php

namespace App\Service;

use App\Entity\Category;
use App\Entity\Product;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class AdminPriceManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ProductRepository $products,
        private ProductPriceCalculator $calculator,
    ) {
    }

    /**
     * @return array{id: int, priceTaxExcluded: string, taxRate: string, priceTaxIncluded: string}
     */
    public function updateProduct(Product $product, string $priceTaxExcluded, string $taxRate): array
    {
        $this->apply($product, $priceTaxExcluded, $taxRate);
        $this->entityManager->flush();

        return $this->present($product);
    }

    /**
     * @return list<array{id: int, priceTaxExcluded: string, taxRate: string, priceTaxIncluded: string}>
     */
    public function updateCategory(Category $category, string $priceTaxExcluded, string $taxRate): array
    {
        $priceTaxExcluded = $this->calculator->normalizeTaxExcluded($priceTaxExcluded);
        $taxRate = $this->calculator->normalizeTaxRate($taxRate);
        $products = $this->products->findByCategoryForPriceAdmin($category);

        foreach ($products as $product) {
            $this->applyNormalized($product, $priceTaxExcluded, $taxRate);
        }

        $this->entityManager->flush();

        return array_map($this->present(...), $products);
    }

    private function apply(Product $product, string $priceTaxExcluded, string $taxRate): void
    {
        $this->applyNormalized(
            $product,
            $this->calculator->normalizeTaxExcluded($priceTaxExcluded),
            $this->calculator->normalizeTaxRate($taxRate),
        );
    }

    private function applyNormalized(Product $product, string $priceTaxExcluded, string $taxRate): void
    {
        $product
            ->setPriceTaxExcluded($priceTaxExcluded)
            ->setTaxRate($taxRate)
            ->setPriceTaxIncluded($this->calculator->taxIncluded($priceTaxExcluded, $taxRate));
    }

    /**
     * @return array{id: int, priceTaxExcluded: string, taxRate: string, priceTaxIncluded: string}
     */
    private function present(Product $product): array
    {
        return [
            'id' => (int) $product->getId(),
            'priceTaxExcluded' => number_format((float) $product->getPriceTaxExcluded(), 2, '.', ''),
            'taxRate' => number_format((float) $product->getTaxRate(), 2, '.', ''),
            'priceTaxIncluded' => number_format((float) $product->getPriceTaxIncluded(), 2, '.', ''),
        ];
    }
}
