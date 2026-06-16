<?php

namespace App\Tests\Entity;

use App\Entity\Category;
use App\Entity\License;
use App\Entity\Product;
use App\Entity\ProductImage;
use App\Enum\ProductStatus;
use PHPUnit\Framework\TestCase;

final class ProductTest extends TestCase
{
    public function testSeoMetadataIsGeneratedWhenEmpty(): void
    {
        $product = (new Product())
            ->setName('Figurine Collector Arcane')
            ->setDescription('<p>Une figurine officielle <strong>ULTRAPOP</strong> pour les collectionneurs.</p>');

        $product->completeSeoAndTimestamps();

        self::assertSame('Figurine Collector Arcane', $product->getSeoTitle());
        self::assertSame(
            'Une figurine officielle ULTRAPOP pour les collectionneurs.',
            $product->getSeoDescription(),
        );
    }

    public function testCustomSeoMetadataIsPreserved(): void
    {
        $product = (new Product())
            ->setName('Produit')
            ->setDescription('Description produit')
            ->setSeoTitle('Titre personnalisé')
            ->setSeoDescription('Description personnalisée');

        $product->completeSeoAndTimestamps();

        self::assertSame('Titre personnalisé', $product->getSeoTitle());
        self::assertSame('Description personnalisée', $product->getSeoDescription());
    }

    public function testCategoryAndLicenseRelationsAreSynchronized(): void
    {
        $category = (new Category())->setName('Figurines');
        $license = (new License())->setName('Arcane');
        $product = (new Product())
            ->setCategory($category)
            ->setLicense($license);

        self::assertTrue($category->getProducts()->contains($product));
        self::assertTrue($license->getProducts()->contains($product));
        self::assertSame($category, $product->getCategory());
        self::assertSame($license, $product->getLicense());
    }

    public function testImagesAndCoverAreManagedByTheProduct(): void
    {
        $firstImage = (new ProductImage())
            ->setPath('/uploads/products/front.webp')
            ->setPosition(0);
        $coverImage = (new ProductImage())
            ->setPath('/uploads/products/cover.webp')
            ->setPosition(1)
            ->setCover(true);
        $product = new Product();

        $product->addImage($firstImage)->addImage($coverImage);

        self::assertCount(2, $product->getImages());
        self::assertSame($product, $coverImage->getProduct());
        self::assertSame($coverImage, $product->getCoverImage());

        $product->removeImage($coverImage);

        self::assertNull($coverImage->getProduct());
        self::assertSame($firstImage, $product->getCoverImage());
    }

    public function testProductUsesExactAmountsAndCommercialStatus(): void
    {
        $product = (new Product())
            ->setPriceTaxExcluded('49.916667')
            ->setPriceTaxIncluded('59.900000')
            ->setQuantity(12)
            ->setStatus(ProductStatus::PROMO)
            ->setWidth('10.500')
            ->setHeight('25.000')
            ->setDepth('8.250')
            ->setWeight('0.750');

        self::assertSame('49.916667', $product->getPriceTaxExcluded());
        self::assertSame('59.900000', $product->getPriceTaxIncluded());
        self::assertSame(12, $product->getQuantity());
        self::assertSame(ProductStatus::PROMO, $product->getStatus());
        self::assertTrue($product->isOnSale());
        self::assertFalse($product->isNew());
        self::assertFalse($product->isBestSeller());
        self::assertSame('10.500', $product->getWidth());
        self::assertSame('25.000', $product->getHeight());
        self::assertSame('8.250', $product->getDepth());
        self::assertSame('0.750', $product->getWeight());
    }
}
