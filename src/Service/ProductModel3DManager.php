<?php

namespace App\Service;

use App\Entity\Product;
use App\Entity\ProductModel3D;
use App\Repository\ProductModel3DRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

final readonly class ProductModel3DManager
{
    private const UPLOAD_DIRECTORY = 'assets/img/3dproduct';
    private const STORED_PATH_PREFIX = 'img/3dproduct';
    private const MAX_TEXTURE_SIZE = 5_242_880;
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private ProductModel3DRepository $models,
        private ProductModel3DTypeGuesser $typeGuesser,
        private SluggerInterface $slugger,
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function save(Product $product, array $payload, ?UploadedFile $textureFile = null): ProductModel3D
    {
        $model = $this->models->findOneBy(['product' => $product]) ?? new ProductModel3D();
        $model
            ->setProduct($product)
            ->setActive($this->booleanValue($payload['active'] ?? false))
            ->setModelType($this->modelTypeValue($product, $payload['modelType'] ?? null))
            ->setWidthScale($this->floatValue($payload, 'widthScale'))
            ->setHeight($this->floatValue($payload, 'height'))
            ->setBodyBulge($this->floatValue($payload, 'bodyBulge'))
            ->setShoulder($this->floatValue($payload, 'shoulder'))
            ->setTopCut($this->floatValue($payload, 'topCut'))
            ->setTopNeck($this->floatValue($payload, 'topNeck'))
            ->setBottomNeck($this->floatValue($payload, 'bottomNeck'))
            ->setLidScale($this->floatValue($payload, 'lidScale'));

        if ($textureFile instanceof UploadedFile) {
            $model->setTexturePath($this->uploadTexture($product, $textureFile));
        }

        $this->entityManager->persist($model);
        $this->entityManager->flush();

        return $model;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function floatValue(array $payload, string $key): float
    {
        $rawValue = str_replace(',', '.', trim((string) ($payload[$key] ?? '')));

        if ('' === $rawValue || !is_numeric($rawValue)) {
            throw new \InvalidArgumentException('admin.model_3d.error.invalid_value');
        }

        $value = (float) $rawValue;
        [$min, $max] = ProductModel3D::LIMITS[$key] ?? [null, null];

        if (null === $min || null === $max || $value < $min || $value > $max) {
            throw new \InvalidArgumentException('admin.model_3d.error.out_of_range');
        }

        return $value;
    }

    private function booleanValue(mixed $value): bool
    {
        return in_array($value, [true, 1, '1', 'on', 'true'], true);
    }

    private function modelTypeValue(Product $product, mixed $value): string
    {
        $modelType = trim((string) $value);

        if ('' === $modelType) {
            return $this->typeGuesser->guess($product);
        }

        if (!in_array($modelType, ProductModel3D::TYPES, true)) {
            throw new \InvalidArgumentException('admin.model_3d.error.invalid_model_type');
        }

        return $modelType;
    }

    private function uploadTexture(Product $product, UploadedFile $file): string
    {
        $mimeType = (string) $file->getMimeType();

        if (!isset(self::ALLOWED_MIME_TYPES[$mimeType])) {
            throw new \InvalidArgumentException('admin.model_3d.error.invalid_texture');
        }

        if ($file->getSize() > self::MAX_TEXTURE_SIZE) {
            throw new \InvalidArgumentException('admin.model_3d.error.texture_too_large');
        }

        $targetDirectory = $this->projectDir . '/' . self::UPLOAD_DIRECTORY;

        if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0775, true) && !is_dir($targetDirectory)) {
            throw new \RuntimeException(sprintf('Unable to create "%s".', $targetDirectory));
        }

        $extension = self::ALLOWED_MIME_TYPES[$mimeType];
        $productSlug = strtolower((string) $this->slugger->slug($product->getReference() . '-' . $product->getName()));
        $filename = sprintf('%s-%s.%s', $productSlug, bin2hex(random_bytes(6)), $extension);

        $file->move($targetDirectory, $filename);

        return self::STORED_PATH_PREFIX . '/' . $filename;
    }
}
