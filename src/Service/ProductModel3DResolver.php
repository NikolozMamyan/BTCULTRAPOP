<?php

namespace App\Service;

use App\Entity\Product;
use App\Entity\ProductModel3D;
use App\Repository\ProductModel3DRepository;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class ProductModel3DResolver
{
    /**
     * @var array<int, ProductModel3D|null>
     */
    private array $cache = [];

    public function __construct(
        private readonly ProductModel3DRepository $models,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function resolve(Product $product): ?ProductModel3D
    {
        $productId = $product->getId();

        if (null !== $productId && array_key_exists($productId, $this->cache)) {
            return $this->cache[$productId];
        }

        $model = $this->models->findOneBy(['product' => $product]);

        if (!$model instanceof ProductModel3D || !$model->isActive()) {
            return $this->remember($product, null);
        }

        return $this->remember($product, $model);
    }

    /**
     * @param list<Product> $products
     */
    public function warmup(array $products): void
    {
        $missingProducts = array_values(array_filter(
            $products,
            function (Product $product): bool {
                $productId = $product->getId();

                return null !== $productId && !array_key_exists($productId, $this->cache);
            },
        ));

        if ([] === $missingProducts) {
            return;
        }

        $indexedModels = $this->models->findIndexedByProduct($missingProducts);

        foreach ($missingProducts as $product) {
            $productId = $product->getId();

            if (null === $productId) {
                continue;
            }

            $model = $indexedModels[$productId] ?? null;
            $this->cache[$productId] = $model instanceof ProductModel3D && $model->isActive() ? $model : null;
        }
    }

    /**
     * @return array{type: string, texture: string, shape: array<string, float>}|null
     */
    public function present(Product $product): ?array
    {
        $model = $this->resolve($product);

        if (!$model instanceof ProductModel3D) {
            return null;
        }

        $texture = $this->textureUrl($model);

        if (null === $texture) {
            return null;
        }

        return [
            'type' => $model->getModelType(),
            'texture' => $texture,
            'shape' => $model->toShapeArray(),
        ];
    }

    private function remember(Product $product, ?ProductModel3D $model): ?ProductModel3D
    {
        $productId = $product->getId();

        if (null !== $productId) {
            $this->cache[$productId] = $model;
        }

        return $model;
    }

    private function textureUrl(ProductModel3D $model): ?string
    {
        $filename = $model->getTextureFilename();

        if (null === $filename || '' === $filename) {
            return null;
        }

        return $this->urlGenerator->generate('app_media_3d_product_texture', [
            'filename' => $filename,
        ]);
    }
}
