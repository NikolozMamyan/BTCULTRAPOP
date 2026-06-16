<?php

namespace App\Service;

use App\Entity\Product;
use App\Enum\ProductStatus;
use App\Repository\ProductRepository;

final readonly class StorefrontProductCatalog
{
    private const FALLBACK_IMAGE = 'https://ultrapop.com/img/p/fr-default-large_default.jpg';

    public function __construct(
        private ProductRepository $products,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        return array_map(
            fn (Product $product): array => $this->present($product),
            $this->products->findForStorefront(),
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function onSale(): array
    {
        return array_values(array_filter(
            $this->all(),
            static fn (array $product): bool => 'Promo' === ($product['tag'] ?? null),
        ));
    }

    public function findEntity(int $id): ?Product
    {
        return $this->products->findOneForStorefront($id);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function related(Product $product, int $limit = 3): array
    {
        return array_map(
            fn (Product $relatedProduct): array => $this->present($relatedProduct),
            $this->products->findRelatedForStorefront($product, $limit),
        );
    }

    /**
     * @return list<string>
     */
    public function categories(): array
    {
        return $this->products->findStorefrontCategoryNames();
    }

    public function maxPrice(): int
    {
        return $this->maxPriceFor($this->all());
    }

    /**
     * @param list<array<string, mixed>> $products
     */
    public function maxPriceFor(array $products): int
    {
        $prices = array_map(
            static fn (array $product): float => (float) $product['price'],
            $products,
        );

        return max(1, (int) ceil(max($prices ?: [1])));
    }

    /**
     * @param list<array<string, mixed>> $products
     *
     * @return list<string>
     */
    public function categoriesFor(array $products): array
    {
        $categories = array_values(array_unique(array_map(
            static fn (array $product): string => (string) $product['cat'],
            $products,
        )));
        sort($categories);

        return $categories;
    }

    /**
     * @return array<string, mixed>
     */
    public function present(Product $product): array
    {
        $cover = $product->getCoverImage();
        $quantity = max(0, $product->getQuantity());

        return [
            'id' => $product->getId(),
            'name' => $product->getName(),
            'description' => $product->getDescription(),
            'reference' => $product->getReference(),
            'cat' => $product->getCategory()?->getName() ?? '',
            'license' => $product->getLicense()?->getName() ?? '',
            'price' => (float) $product->getPriceTaxIncluded(),
            'old' => null,
            'img' => $cover?->getPath() ?: self::FALLBACK_IMAGE,
            'quantity' => $quantity,
            'in_stock' => $quantity > 0,
            'rating' => null,
            'pop' => min(100, $quantity),
            'tag' => $this->tagForStatus($product->getStatus()),
        ];
    }

    private function tagForStatus(ProductStatus $status): ?string
    {
        return match ($status) {
            ProductStatus::PROMO => 'Promo',
            ProductStatus::NEW => 'Nouveau',
            ProductStatus::BESTSELLER => 'Bestseller',
            ProductStatus::STANDARD => null,
        };
    }
}
