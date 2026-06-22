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
        'Boissons' => 'https://ultrapop.com/144-product_main_2x/ultrapop-naruto-chibi-naruto-tropical-33cl.jpg',
        'Épicerie salée' => 'https://ultrapop.com/116-default_md/komesan-luffy-one-piece-chips-de-riz-barbecue-60g.jpg',
        'Épicerie sucrée' => 'https://ultrapop.com/60-default_md/yokosan-dragon-ball-super-cereales-miel-350g.jpg',
    ];

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

            if (
                '' === $tree[$parent]['image']
                && true === ($product['thumbnail_is_product'] ?? false)
                && is_string($product['thumbnail'] ?? null)
            ) {
                $tree[$parent]['image'] = $product['thumbnail'];
                $tree[$parent]['fallbackImage'] = is_string($product['img'] ?? null)
                    ? $product['img']
                    : '';
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
            static function (string $name, array $group): array {
                uasort($group['children'], static function (array $first, array $second): int {
                    return $first['position'] <=> $second['position'];
                });

                return [
                    'name' => $name,
                    'count' => $group['count'],
                    'image' => self::CATEGORY_THUMBNAILS[$name] ?? $group['image'],
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
            'name' => $product->getName(),
            'description' => $product->getDescription(),
            'reference' => $product->getReference(),
            'cat' => $category?->getName() ?? '',
            'category_path' => $category?->getPathNames() ?? [],
            'category_position_path' => $this->categoryPositionPath($category),
            'license' => $product->getLicense()?->getName() ?? '',
            'price' => (float) $product->getPriceTaxIncluded(),
            'old' => null,
            'img' => $this->assetUrlResolver->resolve($cover?->getPath() ?: self::FALLBACK_IMAGE),
            'thumbnail' => $this->thumbnailUrl($cover?->getPath()),
            'thumbnail_is_product' => $this->hasProductThumbnail($cover?->getPath()),
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

    private function thumbnailUrl(?string $path): string
    {
        $path = trim((string) $path);

        if (preg_match('~(?:^|/)img/products/(\d+)-large_default\.jpg$~', $path, $matches)) {
            $imageId = $matches[1];

            return sprintf(
                'https://ultrapop.com/img/p/%s/%s-small_default.jpg',
                implode('/', str_split($imageId)),
                $imageId,
            );
        }

        return $this->assetUrlResolver->resolve($path ?: self::FALLBACK_IMAGE)
            ?? $this->assetUrlResolver->resolve(self::FALLBACK_IMAGE)
            ?? '';
    }

    private function hasProductThumbnail(?string $path): bool
    {
        return 1 === preg_match('~(?:^|/)img/products/\d+-large_default\.jpg$~', trim((string) $path));
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
