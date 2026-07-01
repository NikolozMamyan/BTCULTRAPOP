<?php

namespace App\Service;

use App\Entity\Category;
use App\Entity\Product;
use App\Entity\User;
use App\Enum\ProductStatus;
use App\Repository\ProductRepository;
use App\Repository\UserFavoriteRepository;

final readonly class StorefrontProductCatalog
{
    private const FALLBACK_IMAGE = 'img/products/fr-default-large_default.jpg';
    private const CATEGORY_THUMBNAILS = [
        'Boissons' => 'img/categories/boissons-naruto-tropical.png',
        'Épicerie salée' => 'img/categories/epicerie-salee-komesan-luffy.png',
        'Épicerie sucrée' => 'img/categories/epicerie-sucree-yokosan-cereales.jpg',
        'Coffrets' => 'img/categories/coffrets-box-naruto.jpg',
    ];

    public function __construct(
        private ProductRepository $products,
        private UserFavoriteRepository $favorites,
        private AssetUrlResolver $assetUrlResolver,
        private ProductSlugger $productSlugger,
        private ProductModel3DResolver $productModel3DResolver,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function all(?User $user = null): array
    {
        return $this->withFavoriteState($this->presentProducts($this->products->findForStorefront()), $user);
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
        return $this->withFavoriteState($this->presentProducts($this->products->findRelatedForStorefront($product, $limit)), $user);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function favorites(User $user): array
    {
        return $this->withFavoriteState($this->presentProducts($this->products->findFavoritesForStorefront($user)), $user);
    }

    /**
     * @return array<string, mixed>
     */
    public function presentForUser(Product $product, ?User $user = null): array
    {
        $this->productModel3DResolver->warmup([$product]);

        return $this->withFavoriteState([$this->present($product)], $user)[0];
    }

    /**
     * @param list<Product> $products
     *
     * @return list<array<string, mixed>>
     */
    public function presentManyForUser(array $products, ?User $user = null): array
    {
        return $this->withFavoriteState($this->presentProducts($products), $user);
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
     * @return list<array{
     *     name: string,
     *     count: int,
     *     image: string,
     *     fallbackImage: string,
     *     children: list<array{name: string, count: int}>
     * }>
     */
    public function categoriesFor(array $products): array
    {
        $tree = [];

        foreach ($products as $product) {
            $path = array_values(array_filter(
                (array) ($product['category_path'] ?? []),
                static fn (mixed $name): bool => is_string($name) && '' !== trim($name),
            ));
            $positions = array_map('intval', (array) ($product['category_position_path'] ?? []));

            if ('Tout' === ($path[0] ?? null)) {
                array_shift($path);
                array_shift($positions);
            }

            $parent = $path[0] ?? null;

            if (!is_string($parent) || '' === $parent) {
                continue;
            }

            $tree[$parent] ??= [
                'position' => $positions[0] ?? 0,
                'count' => 0,
                'image' => '',
                'fallbackImage' => '',
                'children' => [],
            ];
            ++$tree[$parent]['count'];

            if ('' === $tree[$parent]['image'] && is_string($product['img'] ?? null)) {
                $tree[$parent]['image'] = $product['img'];
                $tree[$parent]['fallbackImage'] = $product['img'];
            }

            $leaf = $path[1] ?? null;

            if (is_string($leaf) && '' !== $leaf) {
                $tree[$parent]['children'][$leaf] ??= [
                    'position' => $positions[1] ?? 0,
                    'count' => 0,
                ];
                ++$tree[$parent]['children'][$leaf]['count'];
            }
        }

        uasort($tree, static function (array $first, array $second): int {
            return $first['position'] <=> $second['position'];
        });

        return array_map(
            function (string $name, array $group): array {
                uasort($group['children'], static function (array $first, array $second): int {
                    return $first['position'] <=> $second['position'];
                });

                return [
                    'name' => $name,
                    'count' => $group['count'],
                    'image' => $this->assetUrlResolver->resolve(self::CATEGORY_THUMBNAILS[$name] ?? '')
                        ?? $group['image'],
                    'fallbackImage' => $group['fallbackImage'],
                    'children' => array_map(
                        static fn (string $childName, array $child): array => [
                            'name' => $childName,
                            'count' => $child['count'],
                        ],
                        array_keys($group['children']),
                        array_values($group['children']),
                    ),
                ];
            },
            array_keys($tree),
            array_values($tree),
        );
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
        $category = $product->getCategory();

        return [
            'id' => $product->getId(),
            'slug' => $this->productSlugger->slug($product),
            'name' => $product->getName(),
            'description' => $product->getDescription(),
            'ingredients' => $product->getIngredients(),
            'seo_title' => $product->getSeoTitle(),
            'seo_description' => $product->getSeoDescription(),
            'reference' => $product->getReference(),
            'ean' => $product->getEan(),
            'cat' => $category?->getName() ?? '',
            'category_path' => $category?->getPathNames() ?? [],
            'category_position_path' => $this->categoryPositionPath($category),
            'model_3d' => $this->productModel3DResolver->present($product),
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
            'updated_at' => $product->getUpdatedAt(),
        ];
    }

    /**
     * @param list<Product> $products
     *
     * @return list<array<string, mixed>>
     */
    private function presentProducts(array $products): array
    {
        $this->productModel3DResolver->warmup($products);

        return array_map(
            fn (Product $product): array => $this->present($product),
            $products,
        );
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
     * @return list<int>
     */
    private function categoryPositionPath(?Category $category): array
    {
        $positions = [];
        $visited = [];

        while ($category instanceof Category) {
            $objectId = spl_object_id($category);

            if (isset($visited[$objectId])) {
                break;
            }

            $visited[$objectId] = true;
            array_unshift($positions, $category->getPosition());
            $category = $category->getParent();
        }

        return $positions;
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
