<?php

namespace App\Service;

use App\Entity\Product;
use App\Repository\ProductRepository;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class StorefrontSearchProvider
{
    private const FALLBACK_IMAGE = 'https://ultrapop.com/img/p/fr-default-large_default.jpg';

    public function __construct(
        private ProductRepository $products,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function search(string $query, int $limit = 8): array
    {
        return array_map(
            fn (Product $product): array => $this->present($product),
            $this->products->searchForStorefront($query, $limit),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Product $product): array
    {
        $quantity = max(0, $product->getQuantity());
        $price = (float) $product->getPriceTaxIncluded();

        return [
            'id' => $product->getId(),
            'name' => $product->getName(),
            'reference' => $product->getReference(),
            'category' => $product->getCategory()?->getName() ?? '',
            'license' => $product->getLicense()?->getName() ?? '',
            'image' => $product->getCoverImage()?->getPath() ?: self::FALLBACK_IMAGE,
            'price' => $price,
            'priceFormatted' => number_format($price, 2, ',', ' ') . ' €',
            'inStock' => $quantity > 0,
            'url' => $this->urlGenerator->generate('app_front_product', ['id' => $product->getId()]),
        ];
    }
}
