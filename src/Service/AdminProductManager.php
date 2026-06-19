<?php

namespace App\Service;

use App\Entity\Product;
use App\Entity\ProductImage;
use Doctrine\ORM\EntityManagerInterface;

final class AdminProductManager
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ProductPriceCalculator $priceCalculator,
    ) {
    }

    public function save(Product $product, ?string $coverImageUrl): void
    {
        $product
            ->setPriceTaxExcluded($this->priceCalculator->normalizeTaxExcluded($product->getPriceTaxExcluded()))
            ->setTaxRate($this->priceCalculator->normalizeTaxRate($product->getTaxRate()))
            ->setPriceTaxIncluded($this->priceCalculator->taxIncluded(
                $product->getPriceTaxExcluded(),
                $product->getTaxRate(),
            ));
        $this->syncCoverImage($product, $coverImageUrl);

        $this->entityManager->persist($product);
        $this->entityManager->flush();
    }

    public function delete(Product $product): void
    {
        $this->entityManager->remove($product);
        $this->entityManager->flush();
    }

    private function syncCoverImage(Product $product, ?string $coverImageUrl): void
    {
        $coverImageUrl = trim((string) $coverImageUrl);
        $coverImage = $product->getCoverImage();

        if ('' === $coverImageUrl) {
            if ($coverImage instanceof ProductImage) {
                $product->removeImage($coverImage);
            }

            return;
        }

        if (!$coverImage instanceof ProductImage) {
            $coverImage = (new ProductImage())
                ->setCover(true)
                ->setPosition(0);
            $product->addImage($coverImage);
        }

        foreach ($product->getImages() as $image) {
            $image->setCover($image === $coverImage);
        }

        $coverImage
            ->setPath($coverImageUrl)
            ->setAlt($product->getName());
    }
}
