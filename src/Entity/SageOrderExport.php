<?php

namespace App\Entity;

use App\Repository\SageOrderExportRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SageOrderExportRepository::class)]
#[ORM\Table(name: 'sage_order_export')]
#[ORM\UniqueConstraint(name: 'UNIQ_SAGE_ORDER_EXPORT_ORDER', columns: ['order_id'])]
class SageOrderExport
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne]
    #[ORM\JoinColumn(name: 'order_id', nullable: false, onDelete: 'CASCADE')]
    private ?Order $customerOrder = null;

    #[ORM\Column(options: ['default' => 200])]
    private int $sageStatusCode = 200;

    #[ORM\Column(type: Types::TEXT)]
    private string $payload = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $sageResponse = null;

    #[ORM\Column]
    private \DateTimeImmutable $sentAt;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->sentAt = new \DateTimeImmutable();
        $this->createdAt = $this->sentAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrder(): ?Order
    {
        return $this->customerOrder;
    }

    public function setOrder(Order $order): self
    {
        $this->customerOrder = $order;

        return $this;
    }

    public function getSageStatusCode(): int
    {
        return $this->sageStatusCode;
    }

    public function setSageStatusCode(int $sageStatusCode): self
    {
        $this->sageStatusCode = $sageStatusCode;

        return $this;
    }

    public function getPayload(): string
    {
        return $this->payload;
    }

    public function setPayload(string $payload): self
    {
        $this->payload = $payload;

        return $this;
    }

    public function getSageResponse(): ?string
    {
        return $this->sageResponse;
    }

    public function setSageResponse(?string $sageResponse): self
    {
        $sageResponse = null === $sageResponse ? null : trim($sageResponse);
        $this->sageResponse = '' === $sageResponse ? null : $sageResponse;

        return $this;
    }

    public function getSentAt(): \DateTimeImmutable
    {
        return $this->sentAt;
    }

    public function setSentAt(\DateTimeImmutable $sentAt): self
    {
        $this->sentAt = $sentAt;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
