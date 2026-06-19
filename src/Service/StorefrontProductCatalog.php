<?php

namespace App\Service;

use App\Entity\Product;
use App\Entity\User;
use App\Enum\ProductStatus;
use App\Repository\ProductRepository;
use App\Repository\UserFavoriteRepository;

final readonly class StorefrontProductCatalog
{
    private const FALLBACK_IMAGE = 'img/products/fr-default-large_default.jpg';

    public function __construct(
        private ProductRepository $products,
        private UserFavoriteRepository $favorites,
        private AssetUrlResolver $assetUrlResolver,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function all(?User $user = null): array
    {
        return $this->withFavoriteState(array_map(
            fn (Product $product): array => $this->present($product),
            $this->products->findForStorefront(),
        ), $user);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function onSale(?User $user = null): array
    {
        return array_values(array_filter(
            $this->all($user),
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
    public function related(Product $product, int $limit = 3, ?User $user = null): array
    {
        return $this->withFavoriteState(array_map(
            fn (Product $relatedProduct): array => $this->present($relatedProduct),
            $this->products->findRelatedForStorefront($product, $limit),
        ), $user);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function favorites(User $user): array
    {
        return $this->withFavoriteState(array_map(
            fn (Product $product): array => $this->present($product),
            $this->products->findFavoritesForStorefront($user),
        ), $user);
    }

    /**
     * @return array<string, mixed>
     */
    public function presentForUser(Product $product, ?User $user = null): array
    {
        return $this->withFavoriteState([$this->present($product)], $user)[0];
    }

    /**
     * @param list<Product> $products
     *
     * @return list<array<string, mixed>>
     */
    public function presentManyForUser(array $products, ?User $user = null): array
    {
        return $this->withFavoriteState(array_map(
            fn (Product $product): array => $this->present($product),
            $products,
        ), $user);
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
        return $this->uniqueSortedValuesFor($products, 'cat');
    }

    /**
     * @param list<array<string, mixed>> $products
     *
     * @return list<string>
     */
    public function licensesFor(array $products): array
    {
        return $this->uniqueSortedValuesFor($products, 'license');
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
            'img' => $this->assetUrlResolver->resolve($cover?->getPath() ?: self::FALLBACK_IMAGE),
            'quantity' => $quantity,
            'in_stock' => $quantity > 0,
            'rating' => null,
            'pop' => min(100, $quantity),
            'tag' => $this->tagForStatus($product->getStatus()),
            'favorite' => false,
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

    /**
     * @param list<array<string, mixed>> $products
     *
     * @return list<string>
     */
    private function uniqueSortedValuesFor(array $products, string $key): array
    {
        $values = array_values(array_unique(array_filter(
            array_map(
                static fn (array $product): string => trim((string) ($product[$key] ?? '')),
                $products,
            ),
            static fn (string $value): bool => '' !== $value,
        )));

        sort($values, SORT_NATURAL | SORT_FLAG_CASE);

        return $values;
    }

    /**
     * @param list<array<string, mixed>> $products
     *
     * @return list<array<string, mixed>>
     */
    private function withFavoriteState(array $products, ?User $user): array
    {
        if (!$user instanceof User || [] === $products) {
            return $products;
        }

        $favoriteIds = array_flip($this->favorites->findProductIdsForUser($user));

        return array_map(
            static function (array $product) use ($favoriteIds): array {
                $product['favorite'] = isset($favoriteIds[(int) $product['id']]);

                return $product;
            },
            $products,
        );
    }
}
