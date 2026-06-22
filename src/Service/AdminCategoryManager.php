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
        if ($category->getDepth() > Category::MAX_DEPTH
            || $category->getParent()?->isDescendantOf($category)
            || ($category->getChildren()->count() > 0 && $category->getProducts()->count() > 0)
            || ($category->getParent() instanceof Category && $category->getParent()->getProducts()->count() > 0)
        ) {
            throw new \InvalidArgumentException('admin.category.error.invalid_hierarchy');
        }

        foreach ($category->getProductsRecursive() as $product) {
            $product->setActive($category->isActive());
        }

        $this->entityManager->persist($category);
        $this->entityManager->flush();
    }
}
