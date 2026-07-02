<?php

namespace App\Service;

use App\Entity\Product;
use App\Entity\ProductImage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class AdminProductManager
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ProductPriceCalculator $priceCalculator,
        private readonly ProductStockSourceWriter $stockSourceWriter,
        private readonly StockSettingsManager $stockSettingsManager,
        private readonly ProductGalleryImageUploader $galleryImageUploader,
    ) {
    }

    /**
     * @param list<UploadedFile> $galleryImages
     * @param list<int> $galleryImagesToDelete
     */
    public function save(Product $product, ?string $coverImageUrl, array $galleryImages = [], array $galleryImagesToDelete = []): void
    {
        $product
            ->setPriceTaxExcluded($this->priceCalculator->normalizeTaxExcluded($product->getPriceTaxExcluded()))
            ->setTaxRate($this->priceCalculator->normalizeTaxRate($product->getTaxRate()))
            ->setPriceTaxIncluded($this->priceCalculator->taxIncluded(
                $product->getPriceTaxExcluded(),
                $product->getTaxRate(),
            ));
        $this->syncCoverImage($product, $coverImageUrl);
        $this->deleteGalleryImages($product, $galleryImagesToDelete);
        $this->addGalleryImages($product, $galleryImages);

        $this->entityManager->persist($product);
        $this->entityManager->flush();
        $this->stockSourceWriter->write($product, $this->stockSettingsManager->activeSource(), $product->getQuantity());
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

    /**
     * @param list<UploadedFile> $galleryImages
     */
    private function addGalleryImages(Product $product, array $galleryImages): void
    {
        $position = $this->nextGalleryPosition($product);

        foreach ($galleryImages as $file) {
            if (!$file instanceof UploadedFile || '' === $file->getClientOriginalName()) {
                continue;
            }

            $product->addImage(
                (new ProductImage())
                    ->setCover(false)
                    ->setPosition($position)
                    ->setPath($this->galleryImageUploader->upload($product, $file))
                    ->setAlt($product->getName()),
            );
            $position += 10;
        }
    }

    /**
     * @param list<int> $imageIds
     */
    private function deleteGalleryImages(Product $product, array $imageIds): void
    {
        $imageIds = array_flip(array_map('intval', $imageIds));

        if ([] === $imageIds) {
            return;
        }

        foreach ($product->getImages()->toArray() as $image) {
            if (!$image instanceof ProductImage || $image->isCover() || !isset($imageIds[(int) $image->getId()])) {
                continue;
            }

            $path = $image->getPath();
            $product->removeImage($image);
            $this->galleryImageUploader->remove($path);
        }
    }

    private function nextGalleryPosition(Product $product): int
    {
        $position = 10;

        foreach ($product->getImages() as $image) {
            $position = max($position, $image->getPosition() + 10);
        }

        return $position;
    }
}
