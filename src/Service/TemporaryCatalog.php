<?php

namespace App\Service;

use App\Entity\Category;
use App\Entity\License;
use App\Entity\Product;
use App\Entity\ProductImage;
use App\Enum\ProductStatus;
use App\Repository\CategoryRepository;
use App\Repository\LicenseRepository;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class TemporaryCatalog
{
    private const IMAGES = [
        'https://ultrapop.com/img/p/1/7/0/170.jpg',
        'https://ultrapop.com/img/p/1/6/7/167.jpg',
        'https://ultrapop.com/img/p/1/4/5/145.jpg',
        'https://ultrapop.com/img/p/1/6/1/161.jpg',
    ];

    private const PRODUCTS = [
        ['id' => 1, 'name' => 'Figurine Collector Arcane', 'cat' => 'Figurines', 'price' => 59.90, 'old' => 79.90, 'img' => self::IMAGES[0], 'rating' => 4.8, 'pop' => 98, 'tag' => 'Promo'],
        ['id' => 2, 'name' => 'Statuette Premium One Piece', 'cat' => 'Figurines', 'price' => 89.90, 'old' => null, 'img' => self::IMAGES[1], 'rating' => 4.9, 'pop' => 95, 'tag' => 'Nouveau'],
        ['id' => 3, 'name' => 'Manga Édition Deluxe', 'cat' => 'Mangas', 'price' => 24.90, 'old' => 29.90, 'img' => self::IMAGES[2], 'rating' => 4.6, 'pop' => 80, 'tag' => 'Promo'],
        ['id' => 4, 'name' => 'Pack Goodies Collector', 'cat' => 'Goodies', 'price' => 34.90, 'old' => null, 'img' => self::IMAGES[3], 'rating' => 4.5, 'pop' => 72, 'tag' => null],
        ['id' => 5, 'name' => 'Figurine Édition Limitée', 'cat' => 'Figurines', 'price' => 74.90, 'old' => 99.90, 'img' => self::IMAGES[1], 'rating' => 4.7, 'pop' => 90, 'tag' => 'Promo'],
        ['id' => 6, 'name' => 'Coffret Manga Intégrale', 'cat' => 'Mangas', 'price' => 109.90, 'old' => null, 'img' => self::IMAGES[2], 'rating' => 4.9, 'pop' => 88, 'tag' => 'Nouveau'],
        ['id' => 7, 'name' => 'Mug Céramique Premium', 'cat' => 'Goodies', 'price' => 19.90, 'old' => 24.90, 'img' => self::IMAGES[3], 'rating' => 4.3, 'pop' => 60, 'tag' => 'Promo'],
        ['id' => 8, 'name' => 'Figurine Mini Collector', 'cat' => 'Figurines', 'price' => 39.90, 'old' => null, 'img' => self::IMAGES[0], 'rating' => 4.4, 'pop' => 68, 'tag' => 'Nouveau'],
    ];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private ProductRepository $products,
        private CategoryRepository $categories,
        private LicenseRepository $licenses,
    ) {
    }

    /**
     * @return list<array{id: int, name: string, cat: string, price: float, old: ?float, img: string, rating: float, pop: int, tag: ?string}>
     */
    public function all(): array
    {
        return self::PRODUCTS;
    }

    /**
     * @return array{id: int, name: string, cat: string, price: float, old: ?float, img: string, rating: float, pop: int, tag: ?string}|null
     */
    public function find(int $id): ?array
    {
        foreach (self::PRODUCTS as $product) {
            if ($product['id'] === $id) {
                return $product;
            }
        }

        return null;
    }

    public function ensureProduct(int $id): ?Product
    {
        $payload = $this->find($id);

        if (null === $payload) {
            return null;
        }

        $reference = $this->referenceForId($id);
        $product = $this->products->findOneBy(['reference' => $reference]) ?? new Product();
        $category = $this->ensureCategory($payload['cat']);
        $license = $this->ensureLicense($this->licenseNameForProduct($payload['name']));

        $product
            ->setReference($reference)
            ->setName($payload['name'])
            ->setDescription(sprintf('Produit temporaire ULTRAPOP : %s.', $payload['name']))
            ->setCategory($category)
            ->setLicense($license)
            ->setPriceTaxExcluded($this->taxExcludedFromTaxIncluded($payload['price']))
            ->setPriceTaxIncluded(number_format($payload['price'], 6, '.', ''))
            ->setQuantity(999)
            ->setStatus($this->statusFromTag($payload['tag']));

        if (null === $product->getCoverImage()) {
            $product->addImage((new ProductImage())
                ->setPath($payload['img'])
                ->setAlt($payload['name'])
                ->setCover(true));
        }

        $this->entityManager->persist($product);

        return $product;
    }

    public function temporaryIdForProduct(Product $product): ?int
    {
        if (!preg_match('/^TEMP-(\d{4})$/', $product->getReference(), $matches)) {
            return null;
        }

        return (int) $matches[1];
    }

    private function ensureCategory(string $name): Category
    {
        $category = $this->categories->findOneBy(['name' => $name]) ?? (new Category())->setName($name);
        $this->entityManager->persist($category);

        return $category;
    }

    private function ensureLicense(string $name): License
    {
        $license = $this->licenses->findOneBy(['name' => $name]) ?? (new License())->setName($name);
        $this->entityManager->persist($license);

        return $license;
    }

    private function referenceForId(int $id): string
    {
        return sprintf('TEMP-%04d', $id);
    }

    private function taxExcludedFromTaxIncluded(float $price): string
    {
        return number_format($price / 1.2, 6, '.', '');
    }

    private function statusFromTag(?string $tag): ProductStatus
    {
        return match ($tag) {
            'Promo' => ProductStatus::PROMO,
            'Nouveau' => ProductStatus::NEW,
            default => ProductStatus::STANDARD,
        };
    }

    private function licenseNameForProduct(string $name): string
    {
        if (str_contains($name, 'One Piece')) {
            return 'One Piece';
        }

        if (str_contains($name, 'Arcane')) {
            return 'Arcane';
        }

        return 'ULTRAPOP';
    }
}
