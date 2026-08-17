<?php

namespace App\Service;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Product;
use App\Enum\OrderStatus;
use App\Enum\PaymentStatus;
use App\Service\Mailer\SimpleMailerService;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\Checkout\Session;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

final readonly class OrderPaymentRecoveryManager
{
    private const RESEND_AFTER = '-24 hours';
    private const PRODUCT_IMAGE_FALLBACK = 'img/products/fr-default-large_default.jpg';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private StripeCheckoutService $stripeCheckout,
        private PromoCodeManager $promoCodeManager,
        private SimpleMailerService $mailer,
        private OrderPaymentLinkSigner $linkSigner,
        private AssetUrlResolver $assetUrlResolver,
    ) {
    }

    /**
     * @return array{available: bool, reason: string|null}
     */
    public function reminderStatus(Order $order): array
    {
        $reason = $this->reminderUnavailableReason($order);

        return [
            'available' => null === $reason,
            'reason' => $reason,
        ];
    }

    public function customerRecoveryUrl(Order $order): ?string
    {
        if (in_array($order->getPaymentStatus(), [PaymentStatus::PAID, PaymentStatus::REFUNDED], true)
            || null !== $order->getPaidAt()
            || Order::PAYMENT_FAILURE_CART_REOPENED === $order->getPaymentFailureReason()
            || OrderStatus::PENDING_PAYMENT !== $order->getStatus()
            || null !== $this->orderContentUnavailableReason($order)
            || !$this->stripeCheckout->isConfigured()
        ) {
            return null;
        }

        return $this->linkSigner->recoveryUrl($order);
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function sendReminder(Order $order): void
    {
        $reason = $this->reminderUnavailableReason($order);

        if (null !== $reason) {
            throw new \InvalidArgumentException($reason);
        }

        $email = (string) $order->getCustomerEmail();
        $paymentUrl = $this->linkSigner->recoveryUrl($order);
        $products = array_map(
            fn (OrderItem $item): array => $this->emailProduct($item),
            $order->getItems()->toArray(),
        );
        $total = $this->formatCents($order->getTotalTaxIncludedCents());

        $this->mailer->sendTemplateMessage(
            subject: sprintf('Ta commande %s est toujours disponible', $order->getOrderNumber()),
            htmlTemplate: 'emails/order_payment_recovery.html.twig',
            context: [
                'customer_name' => $order->getCustomerName(),
                'order_number' => $order->getOrderNumber(),
                'payment_url' => $paymentUrl,
                'products' => $products,
                'total' => $total,
            ],
            textMessage: sprintf(
                "Bonjour %s,\n\nLe paiement de ta commande %s n'a pas été finalisé.\n\n%s\n\nMontant à régler : %s\nRégler ma commande : %s\n\nTu peux aussi reprendre le paiement depuis Profil > Mes commandes.\n\nDès validation du paiement, nous lançons sa préparation pour l'expédier au plus vite.",
                $order->getCustomerName(),
                $order->getOrderNumber(),
                $this->productsTextSummary($products),
                $total,
                $paymentUrl,
            ),
            to: [$email],
        );

        $order->markPaymentReminderSent();
        $this->entityManager->flush();
    }

    public function resumePayment(Order $order): string
    {
        return $this->entityManager->wrapInTransaction(function () use ($order): string {
            $this->entityManager->refresh($order, LockMode::PESSIMISTIC_WRITE);
            $this->assertPayable($order);

            $currentSessionId = $order->getStripeCheckoutSessionId();

            if (null !== $currentSessionId) {
                $session = $this->stripeCheckout->retrieveSession($currentSessionId);
                $paymentStatus = $this->sessionProperty($session, 'payment_status');

                if ('paid' === $paymentStatus || 'complete' === $this->sessionProperty($session, 'status')) {
                    throw new \InvalidArgumentException('checkout.recovery.already_completed');
                }

                if ('open' === $this->sessionProperty($session, 'status')) {
                    $url = $this->sessionProperty($session, 'url');

                    if (null === $url) {
                        throw new \InvalidArgumentException('checkout.recovery.temporary');
                    }

                    $this->prepareOrderForPayment($order);

                    return $url;
                }
            }

            $this->prepareOrderForPayment($order);
            $idempotencyKey = hash('sha256', sprintf(
                'order-payment-recovery:%d:%s',
                $order->getId(),
                $currentSessionId ?? 'first-attempt',
            ));
            $session = $this->stripeCheckout->createSession($order, $idempotencyKey);
            $url = $this->sessionProperty($session, 'url');

            if (null === $url) {
                throw new \InvalidArgumentException('checkout.recovery.temporary');
            }

            $order
                ->setStripeCheckoutSessionId($this->sessionProperty($session, 'id'))
                ->setStripePaymentIntentId($this->sessionObjectId($session, 'payment_intent'))
                ->setStripeCustomerId($this->sessionObjectId($session, 'customer'));

            return $url;
        });
    }

    private function reminderUnavailableReason(Order $order): ?string
    {
        if (!$this->isRecoverable($order)) {
            return in_array($order->getPaymentStatus(), [PaymentStatus::PAID, PaymentStatus::REFUNDED], true)
                ? 'admin.order.recovery.error.already_paid'
                : 'admin.order.recovery.error.not_eligible';
        }

        $email = trim((string) $order->getCustomerEmail());

        if ('' === $email || false === filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'admin.order.recovery.error.no_email';
        }

        if (!$this->stripeCheckout->isConfigured()) {
            return 'admin.order.recovery.error.stripe_unavailable';
        }

        $reason = $this->orderContentUnavailableReason($order);

        if (null !== $reason) {
            return $reason;
        }

        $lastSentAt = $order->getPaymentReminderSentAt();

        if ($lastSentAt instanceof \DateTimeImmutable && $lastSentAt > new \DateTimeImmutable(self::RESEND_AFTER)) {
            return 'admin.order.recovery.error.already_sent';
        }

        return null;
    }

    private function assertPayable(Order $order): void
    {
        if (in_array($order->getPaymentStatus(), [PaymentStatus::PAID, PaymentStatus::REFUNDED], true)
            || null !== $order->getPaidAt()
        ) {
            throw new \InvalidArgumentException('checkout.recovery.already_paid');
        }

        if (Order::PAYMENT_FAILURE_CART_REOPENED === $order->getPaymentFailureReason()) {
            throw new \InvalidArgumentException('checkout.recovery.not_available');
        }

        if (!in_array($order->getStatus(), [OrderStatus::PENDING_PAYMENT, OrderStatus::CANCELLED], true)) {
            throw new \InvalidArgumentException('checkout.recovery.not_available');
        }

        $reason = $this->orderContentUnavailableReason($order);

        if (null !== $reason) {
            throw new \InvalidArgumentException('admin.order.recovery.error.product_unavailable' === $reason
                ? 'checkout.recovery.product_unavailable'
                : 'checkout.recovery.not_available');
        }
    }

    private function orderContentUnavailableReason(Order $order): ?string
    {
        if ($order->getItems()->isEmpty() || $order->getTotalTaxIncludedCents() <= 0) {
            return 'admin.order.recovery.error.empty_order';
        }

        foreach ($order->getItems() as $item) {
            $product = $item->getProduct();

            if (!$product instanceof Product || !$product->isActive() || $product->getQuantity() < $item->getQuantity()) {
                return 'admin.order.recovery.error.product_unavailable';
            }
        }

        return null;
    }

    private function isRecoverable(Order $order): bool
    {
        if (Order::PAYMENT_FAILURE_CART_REOPENED === $order->getPaymentFailureReason()) {
            return false;
        }

        return OrderStatus::PENDING_PAYMENT === $order->getStatus()
            || OrderStatus::CANCELLED === $order->getStatus();
    }

    private function prepareOrderForPayment(Order $order): void
    {
        if (null !== $order->getPromoCode()) {
            $this->promoCodeManager->reserveForOrder($order);
        }

        $order->getCart()?->markConverted();
        $order->markPaymentProcessing();
    }

    /**
     * @return array{name: string, quantity: int, unit_price: string, total: string, image: string|null}
     */
    private function emailProduct(OrderItem $item): array
    {
        return [
            'name' => $item->getProductName(),
            'quantity' => $item->getQuantity(),
            'unit_price' => $this->formatCents($item->getUnitPriceTaxIncludedCents()),
            'total' => $this->formatCents($item->getTotalTaxIncludedCents()),
            'image' => $this->assetUrlResolver->resolveAbsolute(
                $item->getProductImage() ?: self::PRODUCT_IMAGE_FALLBACK,
            ),
        ];
    }

    /**
     * @param list<array{name: string, quantity: int, total: string}> $products
     */
    private function productsTextSummary(array $products): string
    {
        return implode("\n", array_map(
            static fn (array $product): string => sprintf(
                '- %s × %d : %s',
                $product['name'],
                $product['quantity'],
                $product['total'],
            ),
            $products,
        ));
    }

    private function sessionProperty(Session $session, string $property): ?string
    {
        $value = $session->{$property} ?? null;

        return is_scalar($value) && '' !== trim((string) $value) ? (string) $value : null;
    }

    private function sessionObjectId(Session $session, string $property): ?string
    {
        $value = $session->{$property} ?? null;

        if (is_scalar($value) && '' !== trim((string) $value)) {
            return (string) $value;
        }

        if (is_object($value)) {
            $id = $value->id ?? null;

            return is_scalar($id) && '' !== trim((string) $id) ? (string) $id : null;
        }

        return null;
    }

    private function formatCents(int $cents): string
    {
        return number_format($cents / 100, 2, ',', ' ') . ' €';
    }
}
