<?php

namespace App\Entity;

use App\Enum\CartStatus;
use App\Repository\CartRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CartRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Cart
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $user = null;

    #[ORM\Column(length: 64, nullable: true, unique: true)]
    #[Assert\Length(max: 64)]
    private ?string $token = null;

    #[ORM\Column(length: 20, enumType: CartStatus::class)]
    private CartStatus $status = CartStatus::ACTIVE;

    /**
     * @var Collection<int, CartItem>
     */
    #[ORM\OneToMany(
        targetEntity: CartItem::class,
        mappedBy: 'cart',
        cascade: ['persist', 'remove'],
        orphanRemoval: true,
    )]
    private Collection $items;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $expiresAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->items = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
        $this->expiresAt = $this->createdAt->modify('+30 days');
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getToken(): ?string
    {
        return $this->token;
    }

    public function setToken(?string $token): self
    {
        $token = null === $token ? null : trim($token);
        $this->token = '' === $token ? null : $token;

        return $this;
    }

    public function getStatus(): CartStatus
    {
        return $this->status;
    }

    public function setStatus(CartStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function isActive(): bool
    {
        return CartStatus::ACTIVE === $this->status;
    }

    public function markConverted(): self
    {
        $this->status = CartStatus::CONVERTED;

        return $this;
    }

    public function abandon(): self
    {
        $this->status = CartStatus::ABANDONED;

        return $this;
    }

    /**
     * @return Collection<int, CartItem>
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(CartItem $item): self
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setCart($this);
        }

        return $this;
    }

    public function removeItem(CartItem $item): self
    {
        if ($this->items->removeElement($item) && $item->getCart() === $this) {
            $item->setCart(null);
        }

        return $this;
    }

    public function getItemForProduct(Product $product): ?CartItem
    {
        foreach ($this->items as $item) {
            if ($item->getProduct() === $product) {
                return $item;
            }

            if (null !== $product->getId() && $item->getProduct()?->getId() === $product->getId()) {
                return $item;
            }
        }

        return null;
    }

    public function getTotalQuantity(): int
    {
        return array_reduce(
            $this->items->toArray(),
            static fn (int $total, CartItem $item): int => $total + $item->getQuantity(),
            0,
        );
    }

    public function getTotalTaxExcludedCents(): int
    {
        return array_reduce(
            $this->items->toArray(),
            static fn (int $total, CartItem $item): int => $total + $item->getTotalTaxExcludedCents(),
            0,
        );
    }

    public function getTotalTaxIncludedCents(): int
    {
        return array_reduce(
            $this->items->toArray(),
            static fn (int $total, CartItem $item): int => $total + $item->getTotalTaxIncludedCents(),
            0,
        );
    }

    public function getExpiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?\DateTimeImmutable $expiresAt): self
    {
        $this->expiresAt = $expiresAt;

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
