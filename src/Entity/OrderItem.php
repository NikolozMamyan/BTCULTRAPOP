<?php

namespace App\Entity;

use App\Repository\OrderItemRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: OrderItemRepository::class)]
#[ORM\HasLifecycleCallbacks]
class OrderItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?Order $order = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Product $product = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $productName = '';

    #[ORM\Column(length: 64, nullable: true)]
    #[Assert\Length(max: 64)]
    private ?string $productReference = null;

    #[ORM\Column(length: 13, nullable: true)]
    #[Assert\Length(max: 13)]
    private ?string $productEan = null;

    #[ORM\Column(length: 2048, nullable: true)]
    #[Assert\Length(max: 2048)]
    private ?string $productImage = null;

    #[ORM\Column(length: 128, nullable: true)]
    #[Assert\Length(max: 128)]
    private ?string $categoryName = null;

    #[ORM\Column(length: 128, nullable: true)]
    #[Assert\Length(max: 128)]
    private ?string $licenseName = null;

    #[ORM\Column(options: ['default' => 1])]
    #[Assert\Positive]
    private int $quantity = 1;

    #[ORM\Column(options: ['default' => 0])]
    #[Assert\PositiveOrZero]
    private int $unitPriceTaxExcludedCents = 0;

    #[ORM\Column(options: ['default' => 0])]
    #[Assert\PositiveOrZero]
    private int $unitPriceTaxIncludedCents = 0;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, options: ['default' => '0.00'])]
    #[Assert\PositiveOrZero]
    private string $taxRate = '0.00';

    #[ORM\Column(options: ['default' => 0])]
    #[Assert\PositiveOrZero]
    private int $totalTaxExcludedCents = 0;

    #[ORM\Column(options: ['default' => 0])]
    #[Assert\PositiveOrZero]
    private int $totalTaxIncludedCents = 0;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrder(): ?Order
    {
        return $this->order;
    }

    public function setOrder(?Order $order): self
    {
        if ($this->order === $order) {
            return $this;
        }

        $previousOrder = $this->order;
        $this->order = $order;

        $previousOrder?->removeItem($this);
        $order?->addItem($this);

        return $this;
    }

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function setProduct(?Product $product): self
    {
        $this->product = $product;

        return $this;
    }

    public function getProductName(): string
    {
        return $this->productName;
    }

    public function setProductName(string $productName): self
    {
        $this->productName = trim($productName);

        return $this;
    }

    public function getProductReference(): ?string
    {
        return $this->productReference;
    }

    public function setProductReference(?string $productReference): self
    {
        $productReference = null === $productReference ? null : trim($productReference);
        $this->productReference = '' === $productReference ? null : $productReference;

        return $this;
    }

    public function getProductEan(): ?string
    {
        return $this->productEan;
    }

    public function setProductEan(?string $productEan): self
    {
        $productEan = null === $productEan ? null : trim($productEan);
        $this->productEan = '' === $productEan ? null : $productEan;

        return $this;
    }

    public function getProductImage(): ?string
    {
        return $this->productImage;
    }

    public function setProductImage(?string $productImage): self
    {
        $productImage = null === $productImage ? null : trim($productImage);
        $this->productImage = '' === $productImage ? null : $productImage;

        return $this;
    }

    public function getCategoryName(): ?string
    {
        return $this->categoryName;
    }

    public function setCategoryName(?string $categoryName): self
    {
        $categoryName = null === $categoryName ? null : trim($categoryName);
        $this->categoryName = '' === $categoryName ? null : $categoryName;

        return $this;
    }

    public function getLicenseName(): ?string
    {
        return $this->licenseName;
    }

    public function setLicenseName(?string $licenseName): self
    {
        $licenseName = null === $licenseName ? null : trim($licenseName);
        $this->licenseName = '' === $licenseName ? null : $licenseName;

        return $this;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): self
    {
        $this->quantity = max(1, $quantity);
        $this->refreshTotals();

        return $this;
    }

    public function getUnitPriceTaxExcludedCents(): int
    {
        return $this->unitPriceTaxExcludedCents;
    }

    public function setUnitPriceTaxExcludedCents(int $unitPriceTaxExcludedCents): self
    {
        $this->unitPriceTaxExcludedCents = max(0, $unitPriceTaxExcludedCents);
        $this->refreshTotals();

        return $this;
    }

    public function getUnitPriceTaxIncludedCents(): int
    {
        return $this->unitPriceTaxIncludedCents;
    }

    public function setUnitPriceTaxIncludedCents(int $unitPriceTaxIncludedCents): self
    {
        $this->unitPriceTaxIncludedCents = max(0, $unitPriceTaxIncludedCents);
        $this->refreshTotals();

        return $this;
    }

    public function getTaxRate(): string
    {
        return $this->taxRate;
    }

    public function setTaxRate(string $taxRate): self
    {
        $this->taxRate = number_format(max(0, (float) $taxRate), 2, '.', '');

        return $this;
    }

    public function getTotalTaxExcludedCents(): int
    {
        return $this->totalTaxExcludedCents;
    }

    public function getTotalTaxIncludedCents(): int
    {
        return $this->totalTaxIncludedCents;
    }

    public function refreshTotals(): self
    {
        $this->totalTaxExcludedCents = $this->unitPriceTaxExcludedCents * $this->quantity;
        $this->totalTaxIncludedCents = $this->unitPriceTaxIncludedCents * $this->quantity;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    #[ORM\PreUpdate]
    public function updateTimestamp(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
