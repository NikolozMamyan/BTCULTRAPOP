<?php

namespace App\Entity;

use App\Repository\UserSessionRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserSessionRepository::class)]
#[ORM\HasLifecycleCallbacks]
class UserSession
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'sessions')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?User $user = null;

    #[ORM\Column(length: 64, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 64)]
    private string $selector = '';

    #[ORM\Column(length: 64)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 64)]
    private string $tokenHash = '';

    #[ORM\Column(length: 80)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 80)]
    private string $deviceName = 'Appareil inconnu';

    #[ORM\Column(length: 64, nullable: true)]
    #[Assert\Length(max: 64)]
    private ?string $userAgentHash = null;

    #[ORM\Column(length: 45, nullable: true)]
    #[Assert\Length(max: 45)]
    private ?string $ipAddress = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $lastSeenAt;

    #[ORM\Column]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column]
    private \DateTimeImmutable $absoluteExpiresAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $revokedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->lastSeenAt = $this->createdAt;
        $this->expiresAt = $this->createdAt;
        $this->absoluteExpiresAt = $this->createdAt;
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
        if ($this->user === $user) {
            return $this;
        }

        $previousUser = $this->user;
        $this->user = $user;

        $previousUser?->removeSession($this);
        $user?->addSession($this);

        return $this;
    }

    public function getSelector(): string
    {
        return $this->selector;
    }

    public function setSelector(string $selector): self
    {
        $this->selector = $selector;

        return $this;
    }

    public function getTokenHash(): string
    {
        return $this->tokenHash;
    }

    public function setTokenHash(string $tokenHash): self
    {
        $this->tokenHash = $tokenHash;

        return $this;
    }

    public function getDeviceName(): string
    {
        return $this->deviceName;
    }

    public function setDeviceName(string $deviceName): self
    {
        $this->deviceName = trim($deviceName);

        return $this;
    }

    public function getUserAgentHash(): ?string
    {
        return $this->userAgentHash;
    }

    public function setUserAgentHash(?string $userAgentHash): self
    {
        $this->userAgentHash = $userAgentHash;

        return $this;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(?string $ipAddress): self
    {
        $this->ipAddress = $ipAddress;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getLastSeenAt(): \DateTimeImmutable
    {
        return $this->lastSeenAt;
    }

    public function setLastSeenAt(\DateTimeImmutable $lastSeenAt): self
    {
        $this->lastSeenAt = $lastSeenAt;

        return $this;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(\DateTimeImmutable $expiresAt): self
    {
        $this->expiresAt = $expiresAt;

        return $this;
    }

    public function getAbsoluteExpiresAt(): \DateTimeImmutable
    {
        return $this->absoluteExpiresAt;
    }

    public function setAbsoluteExpiresAt(\DateTimeImmutable $absoluteExpiresAt): self
    {
        $this->absoluteExpiresAt = $absoluteExpiresAt;

        return $this;
    }

    public function getRevokedAt(): ?\DateTimeImmutable
    {
        return $this->revokedAt;
    }

    public function revoke(?\DateTimeImmutable $revokedAt = null): self
    {
        $this->revokedAt = $revokedAt ?? new \DateTimeImmutable();

        return $this;
    }

    public function isRevoked(): bool
    {
        return null !== $this->revokedAt;
    }

    public function isExpired(\DateTimeImmutable $now): bool
    {
        return $this->expiresAt <= $now || $this->absoluteExpiresAt <= $now;
    }
}
