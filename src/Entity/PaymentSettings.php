<?php

namespace App\Entity;

use App\Repository\PaymentSettingsRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PaymentSettingsRepository::class)]
#[ORM\HasLifecycleCallbacks]
class PaymentSettings
{
    public const MODE_SANDBOX = 'sandbox';
    public const MODE_LIVE = 'live';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 16, options: ['default' => self::MODE_SANDBOX])]
    #[Assert\Choice(choices: [self::MODE_SANDBOX, self::MODE_LIVE])]
    private string $stripeMode = self::MODE_SANDBOX;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStripeMode(): string
    {
        return $this->stripeMode;
    }

    public function setStripeMode(string $stripeMode): self
    {
        $stripeMode = trim($stripeMode);

        if (!in_array($stripeMode, [self::MODE_SANDBOX, self::MODE_LIVE], true)) {
            throw new \InvalidArgumentException('payment.error.invalid_mode');
        }

        $this->stripeMode = $stripeMode;

        return $this;
    }

    public function isSandbox(): bool
    {
        return self::MODE_SANDBOX === $this->stripeMode;
    }

    public function isLive(): bool
    {
        return self::MODE_LIVE === $this->stripeMode;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function refreshUpdatedAt(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
