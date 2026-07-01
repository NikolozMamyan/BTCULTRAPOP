<?php

namespace App\Service;

use App\Repository\CartRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class AdminCartManager
{
    public function __construct(
        private CartRepository $carts,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function delete(int $cartId): void
    {
        $cart = $this->carts->find($cartId);

        if (null === $cart) {
            throw new \InvalidArgumentException('admin.cart.delete.flash.not_found');
        }

        $this->entityManager->remove($cart);
        $this->entityManager->flush();
    }
}
