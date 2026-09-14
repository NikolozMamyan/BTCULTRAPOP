<?php

namespace App\Service;

use App\Entity\Cart;
use App\Entity\CartItem;
use App\Entity\Order;
use App\Entity\PromoCode;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

final readonly class PromoCodeManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ShippingRateCalculator $shippingRateCalculator,
        private ?TaxAmountCalculator $taxCalculator = null,
    ) {
    }

    public function apply(Cart $cart, PromoCode $promoCode, ?User $user): int
    {
        $error = $this->validationError($promoCode, $user);

        if (null !== $error) {
            throw new \InvalidArgumentException($error);
        }

        $discount = $this->calculateDiscountAmounts($cart, $promoCode)['taxIncludedCents'];

        if ($discount <= 0) {
            throw new \InvalidArgumentException($this->ineligibleAmountMessage($promoCode));
        }

        $cart->setPromoCode($promoCode);

        return $discount;
    }

    public function remove(Cart $cart): void
    {
        $cart->setPromoCode(null);
    }

    public function discountForCart(Cart $cart, bool $strict = false): int
    {
        $promoCode = $cart->getPromoCode();

        if (!$promoCode instanceof PromoCode) {
            return 0;
        }

        $error = $this->validationError($promoCode, $cart->getUser());

        if (null !== $error) {
            if ($strict) {
                throw new \InvalidArgumentException($error);
            }

            return 0;
        }

        $discount = $this->calculateDiscountAmounts($cart, $promoCode)['taxIncludedCents'];

        if ($strict && $discount <= 0) {
            throw new \InvalidArgumentException($this->ineligibleAmountMessage($promoCode));
        }

        return $discount;
    }

    public function discountTaxExcludedForCart(Cart $cart, bool $strict = false): int
    {
        return $this->discountAmountsForCart($cart, $strict)['taxExcludedCents'];
    }

    /**
     * @return array{taxExcludedCents: int, taxIncludedCents: int}
     */
    public function discountAmountsForCart(Cart $cart, bool $strict = false): array
    {
        $promoCode = $cart->getPromoCode();

        if (!$promoCode instanceof PromoCode) {
            return ['taxExcludedCents' => 0, 'taxIncludedCents' => 0];
        }

        $error = $this->validationError($promoCode, $cart->getUser());

        if (null !== $error) {
            if ($strict) {
                throw new \InvalidArgumentException($error);
            }

            return ['taxExcludedCents' => 0, 'taxIncludedCents' => 0];
        }

        $discount = $this->calculateDiscountAmounts($cart, $promoCode);

        if ($strict && $discount['taxExcludedCents'] <= 0) {
            throw new \InvalidArgumentException($this->ineligibleAmountMessage($promoCode));
        }

        return $discount;
    }

    public function reserveForOrder(Order $order): void
    {
        $promoCode = $order->getPromoCode();

        if (!$promoCode instanceof PromoCode || $order->isPromoReservationActive()) {
            return;
        }

        $error = $this->validationError($promoCode, $order->getUser());

        if (null !== $error) {
            throw new \InvalidArgumentException($error);
        }

        $affectedRows = $this->entityManager->getConnection()->executeStatement(
            'UPDATE promo_code
             SET reserved_count = reserved_count + 1
             WHERE id = :id
               AND active = 1
               AND (max_uses IS NULL OR used_count + reserved_count < max_uses)',
            ['id' => $promoCode->getId()],
        );

        if (1 !== $affectedRows) {
            throw new \InvalidArgumentException('promo.flash.exhausted');
        }

        $this->entityManager->refresh($promoCode);
        $order->markPromoReservationActive();
    }

    public function redeemForOrder(Order $order): void
    {
        $promoCode = $order->getPromoCode();

        if (!$promoCode instanceof PromoCode || $order->isPromoUsageRecorded()) {
            return;
        }

        if ($order->isPromoReservationActive()) {
            $this->entityManager->getConnection()->executeStatement(
                'UPDATE promo_code
                 SET reserved_count = GREATEST(0, reserved_count - 1),
                     used_count = used_count + 1
                 WHERE id = :id',
                ['id' => $promoCode->getId()],
            );
        } else {
            $affectedRows = $this->entityManager->getConnection()->executeStatement(
                'UPDATE promo_code
                 SET used_count = used_count + 1
                 WHERE id = :id
                   AND (max_uses IS NULL OR used_count + reserved_count < max_uses)',
                ['id' => $promoCode->getId()],
            );

            if (1 !== $affectedRows) {
                throw new \InvalidArgumentException('promo.flash.exhausted');
            }
        }

        $this->entityManager->refresh($promoCode);
        $order->markPromoUsageRecorded();
    }

    public function releaseForOrder(Order $order): void
    {
        $promoCode = $order->getPromoCode();

        if (!$promoCode instanceof PromoCode || !$order->isPromoReservationActive() || $order->isPromoUsageRecorded()) {
            return;
        }

        $this->entityManager->getConnection()->executeStatement(
            'UPDATE promo_code
             SET reserved_count = GREATEST(0, reserved_count - 1)
             WHERE id = :id',
            ['id' => $promoCode->getId()],
        );
        $this->entityManager->refresh($promoCode);
        $order->releasePromoReservation();
    }

    private function validationError(PromoCode $promoCode, ?User $user): ?string
    {
        if (!$promoCode->isActive()) {
            return 'promo.flash.inactive';
        }

        $now = new \DateTimeImmutable();

        if ($promoCode->getValidFrom() instanceof \DateTimeImmutable && $now < $promoCode->getValidFrom()) {
            return 'promo.flash.not_started';
        }

        if ($promoCode->getValidUntil() instanceof \DateTimeImmutable && $now > $promoCode->getValidUntil()) {
            return 'promo.flash.expired';
        }

        if (!$promoCode->hasAvailableUse()) {
            return 'promo.flash.exhausted';
        }

        $assignedUser = $promoCode->getAssignedUser();

        if ($assignedUser instanceof User) {
            if (!$user instanceof User) {
                return 'promo.flash.login_required';
            }

            if ($assignedUser !== $user
                && (null === $assignedUser->getId() || $assignedUser->getId() !== $user->getId())
            ) {
                return 'promo.flash.not_assigned';
            }
        }

        return null;
    }

    /**
     * @return array{taxExcludedCents: int, taxIncludedCents: int}
     */
    private function calculateDiscountAmounts(Cart $cart, PromoCode $promoCode): array
    {
        $shippingQuote = $this->shippingRateCalculator->quote($cart->getTotalTaxIncludedCents());
        $eligibleAmountCents = $promoCode->appliesToShipping()
            ? $shippingQuote['amountTaxExcludedCents']
            : $cart->getTotalTaxExcludedCents();
        $discountTaxExcludedCents = $promoCode->calculateDiscountCents($eligibleAmountCents);

        if ($discountTaxExcludedCents <= 0) {
            return ['taxExcludedCents' => 0, 'taxIncludedCents' => 0];
        }

        $discountTaxIncludedCents = $promoCode->appliesToShipping()
            ? $this->taxCalculator()->taxIncluded($discountTaxExcludedCents, TaxAmountCalculator::SHIPPING_TAX_RATE)
            : $this->taxCalculator()->taxIncludedDiscount(
                $discountTaxExcludedCents,
                array_map(
                    static fn (CartItem $item): array => [
                        'taxExcludedCents' => $item->getTotalTaxExcludedCents(),
                        'taxRate' => $item->getProduct()?->getTaxRate() ?? '0.00',
                    ],
                    $cart->getItems()->toArray(),
                ),
            );

        return [
            'taxExcludedCents' => $discountTaxExcludedCents,
            'taxIncludedCents' => $discountTaxIncludedCents,
        ];
    }

    private function ineligibleAmountMessage(PromoCode $promoCode): string
    {
        return $promoCode->appliesToShipping()
            ? 'promo.flash.shipping_already_free'
            : 'promo.flash.cart_too_small';
    }

    private function taxCalculator(): TaxAmountCalculator
    {
        return $this->taxCalculator ?? new TaxAmountCalculator();
    }
}
