<?php

namespace App\Service;

use App\Entity\Product;
use Symfony\Component\String\Slugger\AsciiSlugger;

final class ProductSlugger
{
    private readonly AsciiSlugger $slugger;

    public function __construct()
    {
        $this->slugger = new AsciiSlugger('fr');
    }

    public function slug(Product|string $product): string
    {
        $name = $product instanceof Product ? $product->getName() : $product;
        $slug = $this->slugger->slug($name)->lower()->toString();

        return '' !== $slug ? $slug : 'produit-ultrapop';
    }

    /**
     * @return array{id: int, slug: string}
     */
    public function routeParameters(Product $product): array
    {
        $id = $product->getId();

        if (null === $id) {
            throw new \LogicException('A persisted product is required to generate its storefront URL.');
        }

        return [
            'id' => $id,
            'slug' => $this->slug($product),
        ];
    }
}
