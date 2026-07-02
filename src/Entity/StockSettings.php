<?php

namespace App\Entity;

use App\Enum\StockSource;
use App\Repository\StockSettingsRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: StockSettingsRepository::class)]
#[ORM\HasLifecycleCallbacks]
class StockSettings
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20, options: ['default' => 'bureau'])]
    #[Assert\Choice(choices: ['bureau', 'clic'])]
    private string $activeSource = 'bureau';

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

    public function getActiveSource(): string
    {
        return $this->activeSource;
    }

    public function getActiveStockSource(): StockSource
    {
        return StockSource::tryFrom($this->activeSource) ?? StockSource::default();
    }

    public function setActiveSource(string $activeSource): self
    {
        $source = StockSource::tryFrom(trim($activeSource));

        if (!$source instanceof StockSource) {
            throw new \InvalidArgumentException('admin.stock.error.invalid_source');
        }

        $this->activeSource = $source->value;

        return $this;
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function refreshUpdatedAt(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
