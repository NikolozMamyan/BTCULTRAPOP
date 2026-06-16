<?php

namespace App\Entity;

use App\Repository\CartItemRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CartItemRepository::class)]
#[ORM\HasLifecycleCallbacks]
class CartItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?Cart $cart = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    private ?Product $product = null;

    #[ORM\Column(options: ['default' => 1])]
    #[Assert\Positive]
    private int $quantity = 1;

    #[ORM\Column(options: ['default' => 0])]
    #[Assert\PositiveOrZero]
    private int $unitPriceTaxExcludedCents = 0;

    #[ORM\Column(options: ['default' => 0])]
    #[Assert\PositiveOrZero]
    private int $unitPriceTaxIncludedCents = 0;

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

    public function getCart(): ?Cart
    {
        return $this->cart;
    }

    public function setCart(?Cart $cart): self
    {
        if ($this->cart === $cart) {
            return $this;
        }

        $previousCart = $this->cart;
        $this->cart = $cart;

        $previousCart?->removeItem($this);
        $cart?->addItem($this);

        return $this;
    }

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function setProduct(Product $product): self
    {
        $this->product = $product;

        return $this;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): self
    {
        $this->quantity = max(1, $quantity);

        return $this;
    }

    public function incrementQuantity(int $quantity): self
    {
        if ($quantity > 0) {
            $this->quantity += $quantity;
        }

        return $this;
    }

    public function getUnitPriceTaxExcludedCents(): int
    {
        return $this->unitPriceTaxExcludedCents;
    }

    public function setUnitPriceTaxExcludedCents(int $unitPriceTaxExcludedCents): self
    {
        $this->unitPriceTaxExcludedCents = max(0, $unitPriceTaxExcludedCents);

        return $this;
    }

    public function getUnitPriceTaxIncludedCents(): int
    {
        return $this->unitPriceTaxIncludedCents;
    }

    public function setUnitPriceTaxIncludedCents(int $unitPriceTaxIncludedCents): self
    {
        $this->unitPriceTaxIncludedCents = max(0, $unitPriceTaxIncludedCents);

        return $this;
    }

    public function getTotalTaxExcludedCents(): int
    {
        return $this->unitPriceTaxExcludedCents * $this->quantity;
    }

    public function getTotalTaxIncludedCents(): int
    {
        return $this->unitPriceTaxIncludedCents * $this->quantity;
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
