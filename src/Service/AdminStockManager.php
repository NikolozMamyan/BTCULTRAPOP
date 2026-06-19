<?php

namespace App\Service;

use App\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;

final readonly class AdminStockManager
{
    private const MAX_QUANTITY = 2_147_483_647;

    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    /**
     * @return array{id: int, quantity: int}
     */
    public function updateProduct(Product $product, string $quantity): array
    {
        $quantity = trim($quantity);

        if (!preg_match('/^\d+$/', $quantity) || (float) $quantity > self::MAX_QUANTITY) {
            throw new \InvalidArgumentException('admin.stock.error.invalid_quantity');
        }

        $product->setQuantity((int) $quantity);
        $this->entityManager->flush();

        return [
            'id' => (int) $product->getId(),
            'quantity' => $product->getQuantity(),
        ];
    }
}
