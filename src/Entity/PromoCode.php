<?php

namespace App\Entity;

use App\Enum\PromoDiscountType;
use App\Repository\PromoCodeRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: PromoCodeRepository::class)]
#[ORM\Table(name: 'promo_code')]
#[ORM\UniqueConstraint(name: 'UNIQ_PROMO_CODE', columns: ['code'])]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['code'], message: 'admin.promo.error.code_exists')]
class PromoCode
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 3, max: 50)]
    #[Assert\Regex(pattern: '/^[A-Z0-9_-]+$/', message: 'admin.promo.error.invalid_code')]
    private string $code = '';

    #[ORM\Column(length: 20, enumType: PromoDiscountType::class)]
    private PromoDiscountType $discountType = PromoDiscountType::PERCENTAGE;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Assert\Positive]
    private string $value = '10.00';

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $validFrom = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $validUntil = null;

    #[ORM\Column(nullable: true)]
    #[Assert\Positive]
    private ?int $maxUses = null;

    #[ORM\Column(options: ['default' => 0])]
    #[Assert\PositiveOrZero]
    private int $usedCount = 0;

    #[ORM\Column(options: ['default' => 0])]
    #[Assert\PositiveOrZero]
    private int $reservedCount = 0;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $assignedUser = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $active = true;

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

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): self
    {
        $this->code = mb_strtoupper(trim($code));

        return $this;
    }

    public function getDiscountType(): PromoDiscountType
    {
        return $this->discountType;
    }

    public function setDiscountType(PromoDiscountType $discountType): self
    {
        $this->discountType = $discountType;

        return $this;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function setValue(string|int|float $value): self
    {
        $this->value = number_format(max(0, (float) $value), 2, '.', '');

        return $this;
    }

    public function getValidFrom(): ?\DateTimeImmutable
    {
        return $this->validFrom;
    }

    public function setValidFrom(?\DateTimeImmutable $validFrom): self
    {
        $this->validFrom = $validFrom;

        return $this;
    }

    public function getValidUntil(): ?\DateTimeImmutable
    {
        return $this->validUntil;
    }

    public function setValidUntil(?\DateTimeImmutable $validUntil): self
    {
        $this->validUntil = $validUntil;

        return $this;
    }

    public function getMaxUses(): ?int
    {
        return $this->maxUses;
    }

    public function setMaxUses(?int $maxUses): self
    {
        $this->maxUses = null === $maxUses || $maxUses <= 0 ? null : $maxUses;

        return $this;
    }

    public function getUsedCount(): int
    {
        return $this->usedCount;
    }

    public function getReservedCount(): int
    {
        return $this->reservedCount;
    }

    public function getAssignedUser(): ?User
    {
        return $this->assignedUser;
    }

    public function setAssignedUser(?User $assignedUser): self
    {
        $this->assignedUser = $assignedUser;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): self
    {
        $this->active = $active;

        return $this;
    }

    public function isWithinValidityPeriod(?\DateTimeImmutable $at = null): bool
    {
        $at ??= new \DateTimeImmutable();

        return (null === $this->validFrom || $at >= $this->validFrom)
            && (null === $this->validUntil || $at <= $this->validUntil);
    }

    public function hasAvailableUse(): bool
    {
        return null === $this->maxUses || ($this->usedCount + $this->reservedCount) < $this->maxUses;
    }

    public function isAvailableFor(?User $user, ?\DateTimeImmutable $at = null): bool
    {
        if (!$this->active || !$this->isWithinValidityPeriod($at) || !$this->hasAvailableUse()) {
            return false;
        }

        if (!$this->assignedUser instanceof User) {
            return true;
        }

        if (!$user instanceof User) {
            return false;
        }

        if ($this->assignedUser === $user) {
            return true;
        }

        return null !== $user->getId() && $this->assignedUser->getId() === $user->getId();
    }

    public function calculateDiscountCents(int $subtotalCents): int
    {
        $subtotalCents = max(0, $subtotalCents);

        if ($subtotalCents <= 50) {
            return 0;
        }

        $discount = PromoDiscountType::PERCENTAGE === $this->discountType
            ? (int) round($subtotalCents * ((float) $this->value / 100))
            : (int) round((float) $this->value * 100);

        return min(max(0, $discount), $subtotalCents - 50);
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    #[Assert\Callback]
    public function validateConfiguration(ExecutionContextInterface $context): void
    {
        if (PromoDiscountType::PERCENTAGE === $this->discountType && (float) $this->value > 100) {
            $context->buildViolation('admin.promo.error.percentage_too_high')
                ->atPath('value')
                ->addViolation();
        }

        if ($this->validFrom instanceof \DateTimeImmutable
            && $this->validUntil instanceof \DateTimeImmutable
            && $this->validUntil <= $this->validFrom
        ) {
            $context->buildViolation('admin.promo.error.invalid_dates')
                ->atPath('validUntil')
                ->addViolation();
        }

        if (null !== $this->maxUses && $this->maxUses < ($this->usedCount + $this->reservedCount)) {
            $context->buildViolation('admin.promo.error.max_uses_below_usage')
                ->atPath('maxUses')
                ->addViolation();
        }
    }

    #[ORM\PreUpdate]
    public function updateTimestamp(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
