<?php

namespace App\Service;

use App\Entity\Product;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

final readonly class ProductGalleryImageUploader
{
    private const STORED_PATH_PREFIX = 'uploads/products/gallery';
    private const MAX_SIZE = 5_242_880;
    private const MIME_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function __construct(
        #[Autowire('%kernel.project_dir%/public/uploads/products/gallery')]
        private string $uploadDirectory,
        private Filesystem $filesystem,
        private SluggerInterface $slugger,
    ) {
    }

    public function upload(Product $product, UploadedFile $file): string
    {
        if (!$file->isValid()) {
            throw new \InvalidArgumentException('admin.product.gallery.flash.invalid');
        }

        if ($file->getSize() > self::MAX_SIZE) {
            throw new \InvalidArgumentException('admin.product.gallery.flash.too_large');
        }

        $mimeType = (string) $file->getMimeType();
        $extension = self::MIME_EXTENSIONS[$mimeType] ?? null;

        if (null === $extension) {
            throw new \InvalidArgumentException('admin.product.gallery.flash.unsupported');
        }

        $this->filesystem->mkdir($this->uploadDirectory);

        $productSlug = strtolower((string) $this->slugger->slug($product->getReference() . '-' . $product->getName()));
        $filename = sprintf('%s-%s.%s', $productSlug, bin2hex(random_bytes(8)), $extension);

        $file->move($this->uploadDirectory, $filename);

        return self::STORED_PATH_PREFIX . '/' . $filename;
    }

    public function remove(string $path): void
    {
        $path = trim($path);

        if (!str_starts_with($path, self::STORED_PATH_PREFIX . '/')) {
            return;
        }

        $filename = basename($path);
        $absolutePath = sprintf('%s/%s', rtrim($this->uploadDirectory, '/\\'), $filename);

        if ($this->filesystem->exists($absolutePath)) {
            $this->filesystem->remove($absolutePath);
        }
    }
}
