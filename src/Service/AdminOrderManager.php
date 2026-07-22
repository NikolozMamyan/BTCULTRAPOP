<?php

namespace App\Service;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Product;
use App\Enum\OrderStatus;
use App\Enum\PaymentStatus;
use App\Model\AdminManualOrderData;
use App\Model\AdminManualOrderItemData;
use Doctrine\ORM\EntityManagerInterface;

final readonly class AdminOrderManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private OrderNumberGenerator $orderNumberGenerator,
        private ShippingRateCalculator $shippingRateCalculator,
        private PromoCodeManager $promoCodeManager,
        private OrderManager $orderManager,
    ) {
    }

    public function createManual(AdminManualOrderData $data): Order
    {
        return $this->entityManager->wrapInTransaction(function () use ($data): Order {
            $user = $data->customer;
            $defaultAddress = $user?->getDefaultAddress();
            $firstName = $this->valueOrFallback($data->firstName, $user?->getFirstName());
            $lastName = $this->valueOrFallback($data->lastName, $user?->getLastName());
            $customerName = trim(sprintf('%s %s', $firstName, $lastName));
            $manualAddress = '' !== trim($data->street)
                || '' !== trim($data->postalCode)
                || '' !== trim($data->city);

            $order = (new Order())
                ->setOrderNumber($this->orderNumberGenerator->generate())
                ->setUser($user)
                ->setCustomerName($customerName)
                ->setCustomerEmail($this->valueOrFallback($data->email, $user?->getEmail()))
                ->setShippingName($customerName)
                ->setShippingStreet($this->valueOrFallback($data->street, $defaultAddress?->getStreet()))
                ->setShippingPostalCode($this->valueOrFallback($data->postalCode, $defaultAddress?->getPostalCode()))
                ->setShippingCity($this->valueOrFallback($data->city, $defaultAddress?->getCity()))
                ->setShippingCountryCode(
                    $manualAddress
                        ? $data->countryCode
                        : ($defaultAddress?->getCountryCode() ?? $data->countryCode),
                )
                ->setShippingPhone($this->valueOrFallback(
                    $data->phone,
                    $defaultAddress?->getPhone() ?? $user?->getPhone(),
                ));

            foreach ($data->items as $itemData) {
                $order->addItem($this->createItem($itemData));
            }

            $order->refreshTotals();
            $itemsSubtotalCents = $order->getTotalTaxIncludedCents();
            $shippingAmountCents = $data->shippingAmountCents
                ?? $this->shippingRateCalculator->amountForSubtotal($itemsSubtotalCents);
            $discountCents = 0;

            if (null !== $data->promoCode) {
                if (!$data->promoCode->isAvailableFor($user)) {
                    throw new \InvalidArgumentException('admin.order.manual.error.promo_unavailable');
                }

                $eligibleAmountCents = $data->promoCode->appliesToShipping()
                    ? $shippingAmountCents
                    : $itemsSubtotalCents;
                $discountCents = $data->promoCode->calculateDiscountCents($eligibleAmountCents);

                if ($discountCents <= 0) {
                    throw new \InvalidArgumentException('admin.order.manual.error.promo_ineligible');
                }

                $order->setPromoCode($data->promoCode);
            }

            $order
                ->setShippingAmountTaxIncludedCents($shippingAmountCents)
                ->setDiscountAmountTaxIncludedCents($discountCents)
                ->refreshTotals();

            $this->applyInitialStatus($order, $data->status);
            $this->entityManager->persist($order);

            return $order;
        });
    }

    public function updateStatus(Order $order, OrderStatus $status): void
    {
        $this->entityManager->wrapInTransaction(function () use ($order, $status): void {
            if (OrderStatus::PENDING_PAYMENT === $status
                && (null !== $order->getPaidAt()
                    || in_array($order->getPaymentStatus(), [PaymentStatus::PAID, PaymentStatus::REFUNDED], true))
            ) {
                throw new \InvalidArgumentException('admin.order.status.error.cannot_reopen_paid');
            }

            if (OrderStatus::CANCELLED === $status) {
                $this->orderManager->cancel($order);
            } elseif (OrderStatus::REFUNDED === $status) {
                $this->promoCodeManager->releaseForOrder($order);
                $order
                    ->setStatus(OrderStatus::REFUNDED)
                    ->setPaymentStatus(PaymentStatus::REFUNDED);
            } elseif ($this->requiresPaidOrder($status)) {
                $this->applyPaidStatus($order, $status);
            } else {
                $order->setStatus($status);
            }

            $this->entityManager->persist($order);
        });
    }

    public function delete(Order $order): void
    {
        $this->entityManager->wrapInTransaction(function () use ($order): void {
            $this->promoCodeManager->releaseForOrder($order);
            $this->entityManager->remove($order);
        });
    }

    private function createItem(AdminManualOrderItemData $data): OrderItem
    {
        $product = $data->product;

        if (!$product instanceof Product) {
            throw new \InvalidArgumentException('admin.order.manual.error.product_required');
        }

        $taxRate = (float) $product->getTaxRate();
        $unitPriceTaxIncludedCents = $data->unitPriceTaxIncludedCents
            ?? $this->decimalToCents($product->getEffectivePriceTaxIncluded());
        $unitPriceTaxExcludedCents = null === $data->unitPriceTaxIncludedCents
            ? $this->decimalToCents($product->getEffectivePriceTaxExcluded())
            : (int) round($unitPriceTaxIncludedCents / (1 + ($taxRate / 100)));

        return (new OrderItem())
            ->setProduct($product)
            ->setProductName($product->getName())
            ->setProductReference($product->getReference())
            ->setProductEan($product->getEan())
            ->setProductImage($product->getCoverImage()?->getPath())
            ->setCategoryName($product->getCategory()?->getName())
            ->setLicenseName($product->getLicense()?->getName())
            ->setQuantity($data->quantity)
            ->setUnitPriceTaxExcludedCents($unitPriceTaxExcludedCents)
            ->setUnitPriceTaxIncludedCents($unitPriceTaxIncludedCents)
            ->setTaxRate($product->getTaxRate());
    }

    private function applyInitialStatus(Order $order, OrderStatus $status): void
    {
        if (OrderStatus::CANCELLED === $status) {
            $order->cancel();

            return;
        }

        if (OrderStatus::REFUNDED === $status) {
            $order
                ->setStatus(OrderStatus::REFUNDED)
                ->setPaymentStatus(PaymentStatus::REFUNDED);

            return;
        }

        if ($this->requiresPaidOrder($status)) {
            $this->applyPaidStatus($order, $status);

            return;
        }

        if (null !== $order->getPromoCode()) {
            $this->promoCodeManager->reserveForOrder($order);
        }

        $order
            ->setStatus(OrderStatus::PENDING_PAYMENT)
            ->setPaymentStatus(PaymentStatus::PENDING);
    }

    private function requiresPaidOrder(OrderStatus $status): bool
    {
        return in_array($status, [
            OrderStatus::PAID,
            OrderStatus::PREPARATION,
            OrderStatus::SHIPPED,
            OrderStatus::DELIVERED,
        ], true);
    }

    private function applyPaidStatus(Order $order, OrderStatus $status): void
    {
        if (null === $order->getPaidAt() && PaymentStatus::PAID !== $order->getPaymentStatus()) {
            $this->orderManager->markPaid($order);
        } else {
            // A previous payment already applied stock, loyalty and promo effects.
            $order->setPaymentStatus(PaymentStatus::PAID);
        }

        $order->setStatus($status);
    }

    private function decimalToCents(string $amount): int
    {
        return max(0, (int) round((float) str_replace(',', '.', $amount) * 100));
    }

    private function valueOrFallback(?string $value, ?string $fallback): string
    {
        $value = trim((string) $value);

        return '' !== $value ? $value : trim((string) $fallback);
    }
}
