<?php

namespace App\Service;

use App\Entity\Product;
use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;

final readonly class AdminPriceProvider
{
    public function __construct(
        private ProductRepository $products,
        private CategoryRepository $categories,
    ) {
    }

    /**
     * @return array{
     *     products: list<array<string, int|string|bool>>,
     *     categories: list<array<string, int|string|list<array<string, int|string|bool>>>>
     * }
     */
    public function page(): array
    {
        $products = $this->products->findForPriceAdmin();
        $presentedByCategory = [];
        $presentedProducts = [];

        foreach ($products as $product) {
            $presented = $this->presentProduct($product);
            $presentedProducts[] = $presented;
            $categoryId = $product->getCategory()?->getId();

            if (null !== $categoryId) {
                $presentedByCategory[$categoryId][] = $presented;
            }
        }

        $categories = array_map(
            static fn ($category): array => [
                'id' => (int) $category->getId(),
                'name' => $category->getName(),
                'products' => $presentedByCategory[$category->getId()] ?? [],
            ],
            $this->categories->findBy([], ['name' => 'ASC']),
        );

        return [
            'products' => $presentedProducts,
            'categories' => $categories,
        ];
    }

    /**
     * @return array<string, int|string|bool>
     */
    private function presentProduct(Product $product): array
    {
        return [
            'id' => (int) $product->getId(),
            'reference' => $product->getReference(),
            'name' => $product->getName(),
            'category' => $product->getCategory()?->getName() ?? '',
            'active' => $product->isActive(),
            'priceTaxExcluded' => number_format((float) $product->getPriceTaxExcluded(), 2, '.', ''),
            'taxRate' => number_format((float) $product->getTaxRate(), 2, '.', ''),
            'priceTaxIncluded' => number_format((float) $product->getPriceTaxIncluded(), 2, '.', ''),
        ];
    }
}
