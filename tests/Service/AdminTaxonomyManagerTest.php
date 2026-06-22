<?php

namespace App\Tests\Service;

use App\Entity\Category;
use App\Entity\License;
use App\Entity\Product;
use App\Service\AdminCategoryManager;
use App\Service\AdminLicenseManager;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class AdminTaxonomyManagerTest extends TestCase
{
    public function testCategoryManagerAppliesCategoryActiveStateToProducts(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::exactly(2))->method('persist');
        $entityManager->expects(self::exactly(2))->method('flush');

        $manager = new AdminCategoryManager($entityManager);
        $category = new Category();
        $product = new Product();
        $category->addProduct($product);

        $category->setActive(false);
        $product->setActive(true);
        $manager->save($category);
        self::assertFalse($product->isActive());

        $category->setActive(true);
        $manager->save($category);
        self::assertTrue($product->isActive());
    }

    public function testCategoryManagerAppliesParentActiveStateToDescendantProducts(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist');
        $entityManager->expects(self::once())->method('flush');

        $manager = new AdminCategoryManager($entityManager);
        $root = (new Category())->setName('Tout');
        $category = (new Category())->setName('Boissons')->setParent($root);
        $subcategory = (new Category())->setName('Jus')->setParent($category);
        $product = (new Product())->setActive(true);
        $subcategory->addProduct($product);

        $root->setActive(false);
        $manager->save($root);

        self::assertFalse($product->isActive());
        self::assertSame(['Tout', 'Boissons', 'Jus'], $subcategory->getPathNames());
        self::assertSame(2, $subcategory->getDepth());
    }

    public function testLicenseManagerAppliesLicenseActiveStateToProducts(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::exactly(2))->method('persist');
        $entityManager->expects(self::exactly(2))->method('flush');

        $manager = new AdminLicenseManager($entityManager);
        $license = new License();
        $product = new Product();
        $license->addProduct($product);

        $license->setActive(false);
        $product->setActive(true);
        $manager->save($license);
        self::assertFalse($product->isActive());

        $license->setActive(true);
        $manager->save($license);
        self::assertTrue($product->isActive());
    }
}
