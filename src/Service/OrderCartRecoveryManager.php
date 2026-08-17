<?php

namespace App\Service;

use App\Entity\Cart;
use App\Entity\Order;
use App\Entity\Product;
use App\Enum\CartStatus;
use App\Enum\OrderStatus;
use App\Enum\PaymentStatus;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

final readonly class OrderCartRecoveryManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private StripeCheckoutService $stripeCheckout,
        private OrderManager $orderManager,
        private CartManager $cartManager,
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

    public function reopen(Order $order, ?Cart $activeCart = null): Cart
    {
        return $this->entityManager->wrapInTransaction(function () use ($order, $activeCart): Cart {
            $this->entityManager->refresh($order, LockMode::PESSIMISTIC_WRITE);

            $reason = $this->unavailableReason($order);

            if (null !== $reason) {
                throw new \InvalidArgumentException($reason);
            }

            $cart = $order->getCart();

            if (!$cart instanceof Cart || $cart->getItems()->isEmpty()) {
                $cart = $this->rebuildCart($order);
                $order->setCart($cart);
                $this->entityManager->persist($cart);
            }

            $sessionId = $order->getStripeCheckoutSessionId();

            if (null !== $sessionId) {
                if (!$this->stripeCheckout->isConfigured()) {
                    throw new \InvalidArgumentException('checkout.cart_recovery.temporary');
                }

                $session = $this->stripeCheckout->retrieveSession($sessionId);
                $paymentStatus = $this->sessionProperty($session, 'payment_status');
                $sessionStatus = $this->sessionProperty($session, 'status');

                if ('paid' === $paymentStatus) {
                    throw new \InvalidArgumentException('checkout.cart_recovery.already_paid');
                }

                if ('complete' === $sessionStatus) {
                    throw new \InvalidArgumentException('checkout.cart_recovery.already_completed');
                }

                if ('open' === $sessionStatus) {
                    $this->stripeCheckout->expireSession($sessionId);
                }
            }

            $cart
                ->setStatus(CartStatus::ACTIVE)
                ->setExpiresAt(new \DateTimeImmutable('+30 days'));

            if ($activeCart instanceof Cart && $activeCart !== $cart) {
                $this->cartManager->merge($activeCart, $cart);
            }

            $this->cartManager->refreshPrices($cart);
            $this->orderManager->cancel(
                $order,
                reason: Order::PAYMENT_FAILURE_CART_REOPENED,
            );

            return $cart;
        });
    }

    private function unavailableReason(Order $order): ?string
    {
        if (Order::PAYMENT_FAILURE_CART_REOPENED === $order->getPaymentFailureReason()) {
            return 'checkout.cart_recovery.already_reopened';
        }

        if (in_array($order->getPaymentStatus(), [PaymentStatus::PAID, PaymentStatus::REFUNDED], true)
            || null !== $order->getPaidAt()
        ) {
            return 'checkout.cart_recovery.already_paid';
        }

        if (!in_array($order->getStatus(), [OrderStatus::PENDING_PAYMENT, OrderStatus::CANCELLED], true)) {
            return 'checkout.cart_recovery.not_available';
        }

        $cart = $order->getCart();

        if ($cart instanceof Cart && !$cart->getItems()->isEmpty()) {
            return null;
        }

        if ($order->getItems()->isEmpty()) {
            return 'checkout.cart_recovery.not_available';
        }

        foreach ($order->getItems() as $item) {
            $product = $item->getProduct();

            if (!$product instanceof Product || !$product->isActive() || $product->getQuantity() < $item->getQuantity()) {
                return 'checkout.cart_recovery.not_available';
            }
        }

        return null;
    }

    private function rebuildCart(Order $order): Cart
    {
        $cart = $this->cartManager->createCart($order->getUser());

        foreach ($order->getItems() as $item) {
            $product = $item->getProduct();

            if (!$product instanceof Product) {
                throw new \InvalidArgumentException('checkout.cart_recovery.not_available');
            }

            $this->cartManager->addProduct($cart, $product, $item->getQuantity());
        }

        $this->cartManager->assertAvailableForCheckout($cart);

        return $cart;
    }

    private function sessionProperty(object $session, string $property): ?string
    {
        $value = $session->{$property} ?? null;

        return is_scalar($value) && '' !== trim((string) $value) ? (string) $value : null;
    }
}
