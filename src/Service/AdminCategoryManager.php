<?php

namespace App\Service;

use App\Entity\Category;
use Doctrine\ORM\EntityManagerInterface;

final class AdminCategoryManager
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function save(Category $category): void
    {
        foreach ($category->getProducts() as $product) {
            $product->setActive($category->isActive());
        }

        $this->entityManager->persist($category);
        $this->entityManager->flush();
    }
}
