<?php

namespace App\Entity;

use App\Repository\ShippingSettingsRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: ShippingSettingsRepository::class)]
#[ORM\HasLifecycleCallbacks]
class ShippingSettings
{
    public const DEFAULT_MINIMUM_ORDER_CENTS = 1000;

    /**
     * @var list<array{thresholdCents: int, shippingAmountCents: int}>
     */
    public const DEFAULT_TIERS = [
        ['thresholdCents' => 1000, 'shippingAmountCents' => 600],
        ['thresholdCents' => 2000, 'shippingAmountCents' => 475],
        ['thresholdCents' => 3000, 'shippingAmountCents' => 350],
        ['thresholdCents' => 4000, 'shippingAmountCents' => 250],
        ['thresholdCents' => 5000, 'shippingAmountCents' => 0],
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(options: ['default' => self::DEFAULT_MINIMUM_ORDER_CENTS])]
    #[Assert\PositiveOrZero]
    #[Assert\LessThanOrEqual(1000000)]
    private int $minimumOrderCents = self::DEFAULT_MINIMUM_ORDER_CENTS;

    /**
     * @var Collection<int, ShippingRateTier>
     */
    #[ORM\OneToMany(mappedBy: 'settings', targetEntity: ShippingRateTier::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC', 'thresholdCents' => 'ASC'])]
    #[Assert\Valid]
    #[Assert\Count(min: 2, max: 10)]
    private Collection $tiers;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct(bool $withDefaults = true)
    {
        $this->tiers = new ArrayCollection();
        $this->updatedAt = new \DateTimeImmutable();

        if ($withDefaults) {
            foreach (self::DEFAULT_TIERS as $position => $tier) {
                $this->addTier(
                    (new ShippingRateTier())
                        ->setThresholdCents($tier['thresholdCents'])
                        ->setShippingAmountCents($tier['shippingAmountCents'])
                        ->setPosition($position),
                );
            }
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMinimumOrderCents(): int
    {
        return $this->minimumOrderCents;
    }

    public function setMinimumOrderCents(int $minimumOrderCents): self
    {
        $this->minimumOrderCents = $minimumOrderCents;

        return $this;
    }

    /**
     * @return Collection<int, ShippingRateTier>
     */
    public function getTiers(): Collection
    {
        return $this->tiers;
    }

    /**
     * @return list<ShippingRateTier>
     */
    public function getSortedTiers(): array
    {
        $tiers = $this->tiers->toArray();
        usort(
            $tiers,
            static fn (ShippingRateTier $left, ShippingRateTier $right): int =>
                $left->getThresholdCents() <=> $right->getThresholdCents(),
        );

        return array_values($tiers);
    }

    public function addTier(ShippingRateTier $tier): self
    {
        if (!$this->tiers->contains($tier)) {
            $this->tiers->add($tier);
            $tier->setSettings($this);
        }

        return $this;
    }

    public function removeTier(ShippingRateTier $tier): self
    {
        if ($this->tiers->removeElement($tier) && $tier->getSettings() === $this) {
            $tier->setSettings(null);
        }

        return $this;
    }

    public function normalizeTierPositions(): void
    {
        foreach ($this->getSortedTiers() as $position => $tier) {
            $tier->setPosition($position);
        }
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    #[Assert\Callback]
    public function validateConfiguration(ExecutionContextInterface $context): void
    {
        $tiers = $this->getSortedTiers();

        if ([] === $tiers) {
            return;
        }

        if ($tiers[0]->getThresholdCents() !== $this->minimumOrderCents) {
            $context->buildViolation('admin.shipping.error.first_threshold')
                ->atPath('tiers')
                ->addViolation();
        }

        $lastThreshold = null;
        $lastAmount = null;
        $lastIndex = array_key_last($tiers);

        foreach ($tiers as $index => $tier) {
            $threshold = $tier->getThresholdCents();
            $amount = $tier->getShippingAmountCents();

            if (null !== $lastThreshold && $threshold === $lastThreshold) {
                $context->buildViolation('admin.shipping.error.duplicate_threshold')
                    ->atPath('tiers')
                    ->addViolation();
                break;
            }

            if (null !== $lastAmount && $amount > $lastAmount) {
                $context->buildViolation('admin.shipping.error.increasing_price')
                    ->atPath('tiers')
                    ->addViolation();
                break;
            }

            if ($index !== $lastIndex && 0 === $amount) {
                $context->buildViolation('admin.shipping.error.free_must_be_last')
                    ->atPath('tiers')
                    ->addViolation();
                break;
            }

            $lastThreshold = $threshold;
            $lastAmount = $amount;
        }

        if (0 !== $tiers[$lastIndex]->getShippingAmountCents()) {
            $context->buildViolation('admin.shipping.error.free_tier_required')
                ->atPath('tiers')
                ->addViolation();
        }
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function refreshUpdatedAt(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
