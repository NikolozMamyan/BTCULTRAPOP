<?php

namespace App\Service;

use App\Entity\Order;
use App\Entity\Product;
use App\Entity\User;
use App\Enum\OrderStatus;
use App\Enum\PaymentStatus;
use App\Model\CheckoutAddress;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

final readonly class OrderReorderManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CartManager $cartManager,
        private OrderManager $orderManager,
        private ShippingRateCalculator $shippingRateCalculator,
        private StripeCheckoutService $stripeCheckout,
    ) {
    }

    /**
     * @return array{available: bool, reason: string|null}
     */
    public function status(Order $order): array
    {
        $reason = $this->unavailableReason($order);

        return [
            'available' => null === $reason,
            'reason' => $reason,
        ];
    }

    public function createCheckoutOrder(Order $source, User $user): Order
    {
        return $this->entityManager->wrapInTransaction(function () use ($source, $user): Order {
            $this->entityManager->refresh($source, LockMode::PESSIMISTIC_READ);

            if (!$this->sameUser($source->getUser(), $user)) {
                throw new \InvalidArgumentException('profile.order_action.not_found');
            }

            $reason = $this->unavailableReason($source);

            if (null !== $reason) {
                throw new \InvalidArgumentException($reason);
            }

            $cart = $this->cartManager->createCart($user);

            foreach ($source->getItems() as $item) {
                $product = $item->getProduct();
                \assert($product instanceof Product);
                $this->cartManager->addProduct($cart, $product, $item->getQuantity());
            }

            $this->cartManager->assertAvailableForCheckout($cart);
            $shippingQuote = $this->shippingRateCalculator->quote($cart->getTotalTaxIncludedCents());

            if (!$shippingQuote['minimumReached']) {
                throw new \InvalidArgumentException('profile.reorder.error.minimum_order');
            }

            $address = new CheckoutAddress();
            $address->name = $source->getShippingName();
            $address->street = $source->getShippingStreet();
            $address->postalCode = $source->getShippingPostalCode();
            $address->city = $source->getShippingCity();
            $address->countryCode = $source->getShippingCountryCode();
            $address->phone = $source->getShippingPhone();

            $order = $this->orderManager->createGuestFromCart(
                cart: $cart,
                shippingAddress: $address,
                user: $user,
                shippingAmountTaxIncludedCents: $shippingQuote['amountCents'],
            );

            $this->entityManager->persist($cart);
            $this->entityManager->persist($order);

            return $order;
        });
    }

    private function unavailableReason(Order $order): ?string
    {
        if (PaymentStatus::PAID !== $order->getPaymentStatus()
            || null === $order->getPaidAt()
            || !in_array($order->getStatus(), [
                OrderStatus::PAID,
                OrderStatus::PREPARATION,
                OrderStatus::SHIPPED,
                OrderStatus::DELIVERED,
            ], true)
        ) {
            return 'profile.reorder.error.not_paid';
        }

        if (!$this->stripeCheckout->isConfigured()) {
            return 'profile.reorder.error.stripe_unavailable';
        }

        if ($order->getItems()->isEmpty()) {
            return 'profile.reorder.error.empty';
        }

        foreach ($order->getItems() as $item) {
            $product = $item->getProduct();

            if (!$product instanceof Product || !$product->isActive() || $product->getQuantity() < $item->getQuantity()) {
                return 'profile.reorder.error.product_unavailable';
            }
        }

        return null;
    }

    private function sameUser(?User $first, User $second): bool
    {
        return $first === $second
            || (null !== $first?->getId() && $first->getId() === $second->getId());
    }
}
