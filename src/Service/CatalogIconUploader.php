<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

final readonly class CatalogIconUploader
{
    private const STORED_PATH_PREFIX = 'uploads/catalog-icons';
    private const MAX_SIZE = 2_097_152;
    private const MIME_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/svg+xml' => 'svg',
    ];

    public function __construct(
        #[Autowire('%kernel.project_dir%/public/uploads/catalog-icons')]
        private string $uploadDirectory,
        private Filesystem $filesystem,
        private SluggerInterface $slugger,
    ) {
    }

    public function upload(string $type, string $name, UploadedFile $file): string
    {
        if (!$file->isValid()) {
            throw new \InvalidArgumentException('admin.catalog_icon.flash.invalid');
        }

        if ($file->getSize() > self::MAX_SIZE) {
            throw new \InvalidArgumentException('admin.catalog_icon.flash.too_large');
        }

        $mimeType = (string) $file->getMimeType();
        $extension = self::MIME_EXTENSIONS[$mimeType] ?? null;

        if (null === $extension) {
            throw new \InvalidArgumentException('admin.catalog_icon.flash.unsupported');
        }

        $this->filesystem->mkdir($this->uploadDirectory);

        $slug = strtolower((string) $this->slugger->slug($type . '-' . $name));
        $filename = sprintf('%s-%s.%s', $slug, bin2hex(random_bytes(8)), $extension);

        $file->move($this->uploadDirectory, $filename);

        return self::STORED_PATH_PREFIX . '/' . $filename;
    }

    public function remove(?string $path): void
    {
        $path = trim((string) $path);

        if (!str_starts_with($path, self::STORED_PATH_PREFIX . '/')) {
            return;
        }

        $absolutePath = sprintf('%s/%s', rtrim($this->uploadDirectory, '/\\'), basename($path));

        if ($this->filesystem->exists($absolutePath)) {
            $this->filesystem->remove($absolutePath);
        }
    }
}
