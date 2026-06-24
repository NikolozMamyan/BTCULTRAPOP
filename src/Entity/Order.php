<?php

namespace App\Entity;

use App\Enum\OrderStatus;
use App\Enum\PaymentStatus;
use App\Repository\OrderRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: OrderRepository::class)]
#[ORM\Table(name: 'customer_order')]
#[ORM\Index(name: 'IDX_ORDER_STRIPE_INTENT', columns: ['stripe_payment_intent_id'])]
#[ORM\UniqueConstraint(name: 'UNIQ_ORDER_STRIPE_SESSION', columns: ['stripe_checkout_session_id'])]
#[ORM\HasLifecycleCallbacks]
class Order
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 32, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 32)]
    private string $orderNumber = '';

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $user = null;

    #[ORM\Column(length: 30, enumType: OrderStatus::class)]
    private OrderStatus $status = OrderStatus::PENDING_PAYMENT;

    #[ORM\Column(length: 20, enumType: PaymentStatus::class)]
    private PaymentStatus $paymentStatus = PaymentStatus::PENDING;

    #[ORM\Column(length: 3, options: ['default' => 'EUR'])]
    #[Assert\NotBlank]
    #[Assert\Currency]
    private string $currency = 'EUR';

    #[ORM\Column(options: ['default' => 0])]
    #[Assert\PositiveOrZero]
    private int $totalTaxExcludedCents = 0;

    #[ORM\Column(options: ['default' => 0])]
    #[Assert\PositiveOrZero]
    private int $totalTaxIncludedCents = 0;

    #[ORM\Column(options: ['default' => 0])]
    #[Assert\PositiveOrZero]
    private int $shippingAmountTaxIncludedCents = 0;

    #[ORM\Column(options: ['default' => 0])]
    #[Assert\PositiveOrZero]
    private int $discountAmountTaxIncludedCents = 0;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?PromoCode $promoCode = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Assert\Length(max: 50)]
    private ?string $promoCodeSnapshot = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $promoReservationActive = false;

    #[ORM\Column(options: ['default' => false])]
    private bool $promoUsageRecorded = false;

    #[ORM\Column(options: ['default' => 0])]
    #[Assert\PositiveOrZero]
    private int $loyaltyPointsEarned = 0;

    #[ORM\Column(length: 180, nullable: true)]
    #[Assert\Email]
    #[Assert\Length(max: 180)]
    private ?string $customerEmail = null;

    #[ORM\Column(length: 201)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 201)]
    private string $customerName = '';

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private string $shippingName = '';

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $shippingStreet = '';

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 20)]
    private string $shippingPostalCode = '';

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    private string $shippingCity = '';

    #[ORM\Column(length: 2, options: ['default' => 'FR'])]
    #[Assert\NotBlank]
    #[Assert\Country]
    private string $shippingCountryCode = 'FR';

    #[ORM\Column(length: 30, nullable: true)]
    #[Assert\Length(max: 30)]
    private ?string $shippingPhone = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $stripeCheckoutSessionId = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $stripePaymentIntentId = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $stripeCustomerId = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $paymentFailureReason = null;

    /**
     * @var Collection<int, OrderItem>
     */
    #[ORM\OneToMany(
        targetEntity: OrderItem::class,
        mappedBy: 'order',
        cascade: ['persist', 'remove'],
        orphanRemoval: true,
    )]
    private Collection $items;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $paidAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $cancelledAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $confirmationEmailSentAt = null;

    public function __construct()
    {
        $this->items = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrderNumber(): string
    {
        return $this->orderNumber;
    }

    public function setOrderNumber(string $orderNumber): self
    {
        $this->orderNumber = trim($orderNumber);

        return $this;
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

    public function getStatus(): OrderStatus
    {
        return $this->status;
    }

    public function setStatus(OrderStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getPaymentStatus(): PaymentStatus
    {
        return $this->paymentStatus;
    }

    public function setPaymentStatus(PaymentStatus $paymentStatus): self
    {
        $this->paymentStatus = $paymentStatus;

        return $this;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): self
    {
        $this->currency = mb_strtoupper(trim($currency));

        return $this;
    }

    public function getTotalTaxExcludedCents(): int
    {
        return $this->totalTaxExcludedCents;
    }

    public function setTotalTaxExcludedCents(int $totalTaxExcludedCents): self
    {
        $this->totalTaxExcludedCents = max(0, $totalTaxExcludedCents);

        return $this;
    }

    public function getTotalTaxIncludedCents(): int
    {
        return $this->totalTaxIncludedCents;
    }

    public function setTotalTaxIncludedCents(int $totalTaxIncludedCents): self
    {
        $this->totalTaxIncludedCents = max(0, $totalTaxIncludedCents);

        return $this;
    }

    public function getShippingAmountTaxIncludedCents(): int
    {
        return $this->shippingAmountTaxIncludedCents;
    }

    public function setShippingAmountTaxIncludedCents(int $shippingAmountTaxIncludedCents): self
    {
        $this->shippingAmountTaxIncludedCents = max(0, $shippingAmountTaxIncludedCents);

        return $this;
    }

    public function getDiscountAmountTaxIncludedCents(): int
    {
        return $this->discountAmountTaxIncludedCents;
    }

    public function setDiscountAmountTaxIncludedCents(int $discountAmountTaxIncludedCents): self
    {
        $this->discountAmountTaxIncludedCents = max(0, $discountAmountTaxIncludedCents);

        return $this;
    }

    public function getPromoCode(): ?PromoCode
    {
        return $this->promoCode;
    }

    public function setPromoCode(?PromoCode $promoCode): self
    {
        $this->promoCode = $promoCode;
        $this->promoCodeSnapshot = $promoCode?->getCode();

        return $this;
    }

    public function getPromoCodeSnapshot(): ?string
    {
        return $this->promoCodeSnapshot;
    }

    public function isPromoReservationActive(): bool
    {
        return $this->promoReservationActive;
    }

    public function markPromoReservationActive(): self
    {
        $this->promoReservationActive = true;

        return $this;
    }

    public function releasePromoReservation(): self
    {
        $this->promoReservationActive = false;

        return $this;
    }

    public function isPromoUsageRecorded(): bool
    {
        return $this->promoUsageRecorded;
    }

    public function markPromoUsageRecorded(): self
    {
        $this->promoUsageRecorded = true;
        $this->promoReservationActive = false;

        return $this;
    }

    public function getLoyaltyPointsEarned(): int
    {
        return $this->loyaltyPointsEarned;
    }

    public function setLoyaltyPointsEarned(int $loyaltyPointsEarned): self
    {
        $this->loyaltyPointsEarned = max(0, $loyaltyPointsEarned);

        return $this;
    }

    public function getCustomerEmail(): ?string
    {
        return $this->customerEmail;
    }

    public function setCustomerEmail(?string $customerEmail): self
    {
        $customerEmail = null === $customerEmail ? null : mb_strtolower(trim($customerEmail));
        $this->customerEmail = '' === $customerEmail ? null : $customerEmail;

        return $this;
    }

    public function getCustomerName(): string
    {
        return $this->customerName;
    }

    public function setCustomerName(string $customerName): self
    {
        $this->customerName = trim($customerName);

        return $this;
    }

    public function getShippingName(): string
    {
        return $this->shippingName;
    }

    public function setShippingName(string $shippingName): self
    {
        $this->shippingName = trim($shippingName);

        return $this;
    }

    public function getShippingStreet(): string
    {
        return $this->shippingStreet;
    }

    public function setShippingStreet(string $shippingStreet): self
    {
        $this->shippingStreet = trim($shippingStreet);

        return $this;
    }

    public function getShippingPostalCode(): string
    {
        return $this->shippingPostalCode;
    }

    public function setShippingPostalCode(string $shippingPostalCode): self
    {
        $this->shippingPostalCode = trim($shippingPostalCode);

        return $this;
    }

    public function getShippingCity(): string
    {
        return $this->shippingCity;
    }

    public function setShippingCity(string $shippingCity): self
    {
        $this->shippingCity = trim($shippingCity);

        return $this;
    }

    public function getShippingCountryCode(): string
    {
        return $this->shippingCountryCode;
    }

    public function setShippingCountryCode(string $shippingCountryCode): self
    {
        $this->shippingCountryCode = mb_strtoupper(trim($shippingCountryCode));

        return $this;
    }

    public function getShippingPhone(): ?string
    {
        return $this->shippingPhone;
    }

    public function setShippingPhone(?string $shippingPhone): self
    {
        $shippingPhone = null === $shippingPhone ? null : trim($shippingPhone);
        $this->shippingPhone = '' === $shippingPhone ? null : $shippingPhone;

        return $this;
    }

    public function getStripeCheckoutSessionId(): ?string
    {
        return $this->stripeCheckoutSessionId;
    }

    public function setStripeCheckoutSessionId(?string $stripeCheckoutSessionId): self
    {
        $stripeCheckoutSessionId = null === $stripeCheckoutSessionId ? null : trim($stripeCheckoutSessionId);
        $this->stripeCheckoutSessionId = '' === $stripeCheckoutSessionId ? null : $stripeCheckoutSessionId;

        return $this;
    }

    public function getStripePaymentIntentId(): ?string
    {
        return $this->stripePaymentIntentId;
    }

    public function setStripePaymentIntentId(?string $stripePaymentIntentId): self
    {
        $stripePaymentIntentId = null === $stripePaymentIntentId ? null : trim($stripePaymentIntentId);
        $this->stripePaymentIntentId = '' === $stripePaymentIntentId ? null : $stripePaymentIntentId;

        return $this;
    }

    public function getStripeCustomerId(): ?string
    {
        return $this->stripeCustomerId;
    }

    public function setStripeCustomerId(?string $stripeCustomerId): self
    {
        $stripeCustomerId = null === $stripeCustomerId ? null : trim($stripeCustomerId);
        $this->stripeCustomerId = '' === $stripeCustomerId ? null : $stripeCustomerId;

        return $this;
    }

    public function getPaymentFailureReason(): ?string
    {
        return $this->paymentFailureReason;
    }

    public function setPaymentFailureReason(?string $paymentFailureReason): self
    {
        $paymentFailureReason = null === $paymentFailureReason ? null : trim($paymentFailureReason);
        $this->paymentFailureReason = null === $paymentFailureReason || '' === $paymentFailureReason
            ? null
            : mb_substr($paymentFailureReason, 0, 255);

        return $this;
    }

    /**
     * @return Collection<int, OrderItem>
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(OrderItem $item): self
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setOrder($this);
        }

        return $this;
    }

    public function removeItem(OrderItem $item): self
    {
        if ($this->items->removeElement($item) && $item->getOrder() === $this) {
            $item->setOrder(null);
        }

        return $this;
    }

    public function refreshTotals(): self
    {
        $itemsTaxExcluded = array_reduce(
            $this->items->toArray(),
            static fn (int $total, OrderItem $item): int => $total + $item->getTotalTaxExcludedCents(),
            0,
        );
        $itemsTaxIncluded = array_reduce(
            $this->items->toArray(),
            static fn (int $total, OrderItem $item): int => $total + $item->getTotalTaxIncludedCents(),
            0,
        );

        $this->totalTaxExcludedCents = $itemsTaxExcluded;
        $this->totalTaxIncludedCents = max(
            0,
            $itemsTaxIncluded + $this->shippingAmountTaxIncludedCents - $this->discountAmountTaxIncludedCents,
        );
        $this->loyaltyPointsEarned = intdiv($this->totalTaxIncludedCents, 100);

        return $this;
    }

    public function markPaid(?\DateTimeImmutable $paidAt = null): self
    {
        $this->status = OrderStatus::PAID;
        $this->paymentStatus = PaymentStatus::PAID;
        $this->paymentFailureReason = null;
        $this->paidAt = $paidAt ?? new \DateTimeImmutable();

        return $this;
    }

    public function markPaymentProcessing(): self
    {
        $this->status = OrderStatus::PENDING_PAYMENT;
        $this->paymentStatus = PaymentStatus::PROCESSING;
        $this->paymentFailureReason = null;

        return $this;
    }

    public function markPaymentFailed(?string $reason = null): self
    {
        $this->status = OrderStatus::PENDING_PAYMENT;
        $this->paymentStatus = PaymentStatus::FAILED;
        $this->setPaymentFailureReason($reason);

        return $this;
    }

    public function cancel(?\DateTimeImmutable $cancelledAt = null): self
    {
        $this->status = OrderStatus::CANCELLED;
        $this->paymentStatus = PaymentStatus::FAILED;
        $this->cancelledAt = $cancelledAt ?? new \DateTimeImmutable();

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

    public function getPaidAt(): ?\DateTimeImmutable
    {
        return $this->paidAt;
    }

    public function getCancelledAt(): ?\DateTimeImmutable
    {
        return $this->cancelledAt;
    }

    public function getConfirmationEmailSentAt(): ?\DateTimeImmutable
    {
        return $this->confirmationEmailSentAt;
    }

    public function markConfirmationEmailSent(?\DateTimeImmutable $sentAt = null): self
    {
        $this->confirmationEmailSentAt = $sentAt ?? new \DateTimeImmutable();

        return $this;
    }

    #[ORM\PreUpdate]
    public function updateTimestamp(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
