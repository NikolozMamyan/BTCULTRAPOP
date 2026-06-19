<?php

namespace App\Service;

use App\Entity\Product;
use App\Repository\ProductRepository;

final readonly class AdminStockProvider
{
    public function __construct(private ProductRepository $products)
    {
    }

    /**
     * @return list<array{id: int, reference: string, name: string, quantity: int}>
     */
    public function products(): array
    {
        return array_map(
            static fn (Product $product): array => [
                'id' => (int) $product->getId(),
                'reference' => $product->getReference(),
                'name' => $product->getName(),
                'quantity' => $product->getQuantity(),
            ],
            $this->products->findForStockAdmin(),
        );
    }
}
