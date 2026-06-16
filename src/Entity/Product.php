<?php

namespace App\Entity;

use App\Enum\ProductStatus;
use App\Repository\ProductRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ProductRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['reference'])]
class Product
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $name = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 255)]
    #[Assert\Length(max: 255)]
    private string $seoTitle = '';

    #[ORM\Column(length: 512)]
    #[Assert\Length(max: 512)]
    private string $seoDescription = '';

    #[ORM\Column(length: 64, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 64)]
    private string $reference = '';

    #[ORM\ManyToOne(inversedBy: 'products')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    private ?Category $category = null;

    #[ORM\ManyToOne(inversedBy: 'products')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    private ?License $license = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 20, scale: 6)]
    #[Assert\NotBlank]
    #[Assert\PositiveOrZero]
    private string $priceTaxExcluded = '0.000000';

    #[ORM\Column(type: Types::DECIMAL, precision: 20, scale: 6)]
    #[Assert\NotBlank]
    #[Assert\PositiveOrZero]
    private string $priceTaxIncluded = '0.000000';

    #[ORM\Column(options: ['default' => 0])]
    #[Assert\PositiveOrZero]
    private int $quantity = 0;

    #[ORM\Column(length: 20, enumType: ProductStatus::class)]
    private ProductStatus $status = ProductStatus::STANDARD;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 3, nullable: true)]
    #[Assert\PositiveOrZero]
    private ?string $width = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 3, nullable: true)]
    #[Assert\PositiveOrZero]
    private ?string $height = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 3, nullable: true)]
    #[Assert\PositiveOrZero]
    private ?string $depth = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 3, nullable: true)]
    #[Assert\PositiveOrZero]
    private ?string $weight = null;

    /**
     * @var Collection<int, ProductImage>
     */
    #[ORM\OneToMany(
        targetEntity: ProductImage::class,
        mappedBy: 'product',
        cascade: ['persist', 'remove'],
        orphanRemoval: true,
    )]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $images;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->images = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = trim($name);

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = null === $description ? null : trim($description);

        return $this;
    }

    public function getSeoTitle(): string
    {
        return $this->seoTitle;
    }

    public function setSeoTitle(?string $seoTitle): self
    {
        $this->seoTitle = trim($seoTitle ?? '');

        return $this;
    }

    public function getSeoDescription(): string
    {
        return $this->seoDescription;
    }

    public function setSeoDescription(?string $seoDescription): self
    {
        $this->seoDescription = trim($seoDescription ?? '');

        return $this;
    }

    public function getReference(): string
    {
        return $this->reference;
    }

    public function setReference(string $reference): self
    {
        $this->reference = trim($reference);

        return $this;
    }

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(Category $category): self
    {
        if ($this->category === $category) {
            return $this;
        }

        $this->category?->removeProduct($this);
        $this->category = $category;
        $category->addProduct($this);

        return $this;
    }

    public function getLicense(): ?License
    {
        return $this->license;
    }

    public function setLicense(License $license): self
    {
        if ($this->license === $license) {
            return $this;
        }

        $this->license?->removeProduct($this);
        $this->license = $license;
        $license->addProduct($this);

        return $this;
    }

    public function getPriceTaxExcluded(): string
    {
        return $this->priceTaxExcluded;
    }

    public function setPriceTaxExcluded(string $priceTaxExcluded): self
    {
        $this->priceTaxExcluded = $priceTaxExcluded;

        return $this;
    }

    public function getPriceTaxIncluded(): string
    {
        return $this->priceTaxIncluded;
    }

    public function setPriceTaxIncluded(string $priceTaxIncluded): self
    {
        $this->priceTaxIncluded = $priceTaxIncluded;

        return $this;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): self
    {
        $this->quantity = $quantity;

        return $this;
    }

    public function getStatus(): ProductStatus
    {
        return $this->status;
    }

    public function setStatus(ProductStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function isOnSale(): bool
    {
        return ProductStatus::PROMO === $this->status;
    }

    public function isNew(): bool
    {
        return ProductStatus::NEW === $this->status;
    }

    public function isBestSeller(): bool
    {
        return ProductStatus::BESTSELLER === $this->status;
    }

    public function getWidth(): ?string
    {
        return $this->width;
    }

    public function setWidth(?string $width): self
    {
        $this->width = $width;

        return $this;
    }

    public function getHeight(): ?string
    {
        return $this->height;
    }

    public function setHeight(?string $height): self
    {
        $this->height = $height;

        return $this;
    }

    public function getDepth(): ?string
    {
        return $this->depth;
    }

    public function setDepth(?string $depth): self
    {
        $this->depth = $depth;

        return $this;
    }

    public function getWeight(): ?string
    {
        return $this->weight;
    }

    public function setWeight(?string $weight): self
    {
        $this->weight = $weight;

        return $this;
    }

    /**
     * @return Collection<int, ProductImage>
     */
    public function getImages(): Collection
    {
        return $this->images;
    }

    public function addImage(ProductImage $image): self
    {
        if (!$this->images->contains($image)) {
            $this->images->add($image);
            $image->setProduct($this);
        }

        return $this;
    }

    public function removeImage(ProductImage $image): self
    {
        if ($this->images->removeElement($image) && $image->getProduct() === $this) {
            $image->setProduct(null);
        }

        return $this;
    }

    public function getCoverImage(): ?ProductImage
    {
        foreach ($this->images as $image) {
            if ($image->isCover()) {
                return $image;
            }
        }

        return $this->images->first() ?: null;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function completeSeoAndTimestamps(): void
    {
        if ('' === $this->seoTitle) {
            $this->seoTitle = mb_substr($this->name, 0, 255);
        }

        if ('' === $this->seoDescription) {
            $source = strip_tags($this->description ?? $this->name);
            $normalized = preg_replace('/\s+/', ' ', $source) ?? $source;
            $this->seoDescription = mb_substr(trim($normalized), 0, 160);
        }

        $this->updatedAt = new \DateTimeImmutable();
    }
}
