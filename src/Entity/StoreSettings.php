<?php

namespace App\Entity;

use App\Repository\StoreSettingsRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: StoreSettingsRepository::class)]
#[ORM\HasLifecycleCallbacks]
class StoreSettings
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $maintenanceEnabled = false;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $maintenanceStartsAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $maintenanceEndsAt = null;

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

    public function isMaintenanceEnabled(): bool
    {
        return $this->maintenanceEnabled;
    }

    public function setMaintenanceEnabled(bool $maintenanceEnabled): self
    {
        $this->maintenanceEnabled = $maintenanceEnabled;

        return $this;
    }

    public function getMaintenanceStartsAt(): ?\DateTimeImmutable
    {
        return $this->maintenanceStartsAt;
    }

    public function setMaintenanceStartsAt(?\DateTimeImmutable $maintenanceStartsAt): self
    {
        $this->maintenanceStartsAt = $maintenanceStartsAt;

        return $this;
    }

    public function getMaintenanceEndsAt(): ?\DateTimeImmutable
    {
        return $this->maintenanceEndsAt;
    }

    public function setMaintenanceEndsAt(?\DateTimeImmutable $maintenanceEndsAt): self
    {
        $this->maintenanceEndsAt = $maintenanceEndsAt;

        return $this;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    #[Assert\Callback]
    public function validateMaintenanceDates(ExecutionContextInterface $context): void
    {
        if (!$this->maintenanceEnabled) {
            return;
        }

        if (!$this->maintenanceStartsAt instanceof \DateTimeImmutable) {
            $context->buildViolation('admin.store_settings.error.start_required')
                ->atPath('maintenanceStartsAt')
                ->addViolation();
        }

        if (!$this->maintenanceEndsAt instanceof \DateTimeImmutable) {
            $context->buildViolation('admin.store_settings.error.end_required')
                ->atPath('maintenanceEndsAt')
                ->addViolation();
        }

        if ($this->maintenanceStartsAt instanceof \DateTimeImmutable
            && $this->maintenanceEndsAt instanceof \DateTimeImmutable
            && $this->maintenanceEndsAt <= $this->maintenanceStartsAt
        ) {
            $context->buildViolation('admin.store_settings.error.invalid_dates')
                ->atPath('maintenanceEndsAt')
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
