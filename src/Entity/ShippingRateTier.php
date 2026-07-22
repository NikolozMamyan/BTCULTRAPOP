<?php

namespace App\Entity;

use App\Repository\ShippingRateTierRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ShippingRateTierRepository::class)]
#[ORM\Index(name: 'IDX_SHIPPING_TIER_SETTINGS', columns: ['settings_id'])]
#[ORM\Index(name: 'IDX_SHIPPING_TIER_POSITION', columns: ['settings_id', 'position'])]
class ShippingRateTier
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'tiers')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?ShippingSettings $settings = null;

    #[ORM\Column]
    #[Assert\PositiveOrZero]
    #[Assert\LessThanOrEqual(1000000)]
    private int $thresholdCents = 0;

    #[ORM\Column]
    #[Assert\PositiveOrZero]
    #[Assert\LessThanOrEqual(100000)]
    private int $shippingAmountCents = 0;

    #[ORM\Column(options: ['default' => 0])]
    #[Assert\PositiveOrZero]
    private int $position = 0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSettings(): ?ShippingSettings
    {
        return $this->settings;
    }

    public function setSettings(?ShippingSettings $settings): self
    {
        $this->settings = $settings;

        return $this;
    }

    public function getThresholdCents(): int
    {
        return $this->thresholdCents;
    }

    public function setThresholdCents(int $thresholdCents): self
    {
        $this->thresholdCents = $thresholdCents;

        return $this;
    }

    public function getShippingAmountCents(): int
    {
        return $this->shippingAmountCents;
    }

    public function setShippingAmountCents(int $shippingAmountCents): self
    {
        $this->shippingAmountCents = $shippingAmountCents;

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): self
    {
        $this->position = max(0, $position);

        return $this;
    }
}
