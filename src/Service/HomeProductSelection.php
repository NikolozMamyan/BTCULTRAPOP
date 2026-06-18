<?php

namespace App\Service;

use App\Entity\Product;
use App\Entity\User;
use App\Repository\CartItemRepository;
use App\Repository\ProductRepository;

final readonly class HomeProductSelection
{
    private const DEFAULT_LIMIT = 4;
    private const CART_ACTIVITY_WINDOW = '-7 days';

    public function __construct(
        private CartItemRepository $cartItems,
        private ProductRepository $products,
        private StorefrontProductCatalog $catalog,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function products(?User $user = null, int $limit = self::DEFAULT_LIMIT): array
    {
        $limit = max(1, min(8, $limit));
        $trendingIds = $this->cartItems->findTrendingProductIds(
            new \DateTimeImmutable(self::CART_ACTIVITY_WINDOW),
            $limit,
        );
        $selected = $this->sortByIds(
            $this->products->findForStorefrontByIds($trendingIds),
            $trendingIds,
        );
        $selectedIds = array_values(array_filter(array_map(
            static fn (Product $product): ?int => $product->getId(),
            $selected,
        )));

        if (\count($selected) < $limit) {
            $fallback = $this->products->findHomeFallbackForStorefront(
                $selectedIds,
                $limit - \count($selected),
            );
            $selected = [...$selected, ...$fallback];
        }

        return $this->catalog->presentManyForUser(
            \array_slice($selected, 0, $limit),
            $user,
        );
    }

    /**
     * @param list<Product> $products
     * @param list<int>     $orderedIds
     *
     * @return list<Product>
     */
    private function sortByIds(array $products, array $orderedIds): array
    {
        $productsById = [];

        foreach ($products as $product) {
            if (null !== $product->getId()) {
                $productsById[$product->getId()] = $product;
            }
        }

        return array_values(array_filter(array_map(
            static fn (int $id): ?Product => $productsById[$id] ?? null,
            $orderedIds,
        )));
    }
}
