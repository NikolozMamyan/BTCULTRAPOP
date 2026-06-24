<?php

namespace App\Service;

use App\Entity\Order;
use App\Entity\StripeWebhookEvent;
use App\Enum\PaymentStatus;
use App\Repository\OrderRepository;
use App\Repository\StripeWebhookEventRepository;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\Event;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;

final readonly class StripeWebhookHandler
{
    public function __construct(
        private StripeConfigProvider $stripeConfig,
        private StripeWebhookEventRepository $events,
        private OrderRepository $orders,
        private OrderManager $orderManager,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @throws SignatureVerificationException
     */
    public function handle(string $payload, string $signature): ?Order
    {
        $event = Webhook::constructEvent($payload, $signature, $this->stripeConfig->webhookSecret());

        if ($this->events->findOneBy(['eventId' => $event->id]) instanceof StripeWebhookEvent) {
            return $this->handleEvent($event);
        }

        $webhookEvent = (new StripeWebhookEvent())
            ->setEventId((string) $event->id)
            ->setType((string) $event->type);
        $this->entityManager->persist($webhookEvent);

        $order = $this->handleEvent($event);

        $webhookEvent->markProcessed();
        $this->entityManager->flush();

        return $order;
    }

    public function synchronizeCheckoutSession(object $session, string $eventType = 'checkout.session.completed'): ?Order
    {
        $order = $this->resolveOrderFromSession($session);

        if (!$order instanceof Order) {
            return null;
        }

        $order
            ->setStripeCheckoutSessionId($this->stringProperty($session, 'id'))
            ->setStripePaymentIntentId($this->objectIdProperty($session, 'payment_intent'))
            ->setStripeCustomerId($this->objectIdProperty($session, 'customer'));

        $customerEmail = $this->customerEmail($session);

        if (null !== $customerEmail) {
            $order->setCustomerEmail($customerEmail);
        }

        if ('checkout.session.async_payment_failed' === $eventType) {
            $this->orderManager->markPaymentFailed($order, 'stripe.async_payment_failed');

            return $order;
        }

        if ('checkout.session.expired' === $eventType && PaymentStatus::PAID !== $order->getPaymentStatus()) {
            $this->orderManager->cancel($order);

            return $order;
        }

        if ('paid' === $this->stringProperty($session, 'payment_status')) {
            $this->orderManager->markPaid($order);

            return $order;
        }

        $order->markPaymentProcessing();

        return $order;
    }

    private function handleEvent(Event $event): ?Order
    {
        if (!in_array($event->type, [
            'checkout.session.completed',
            'checkout.session.async_payment_succeeded',
            'checkout.session.async_payment_failed',
            'checkout.session.expired',
        ], true)) {
            return null;
        }

        $session = $event->data->object;

        if (!is_object($session)) {
            return null;
        }

        return $this->synchronizeCheckoutSession($session, (string) $event->type);
    }

    private function resolveOrderFromSession(object $session): ?Order
    {
        $metadata = $this->metadata($session);
        $orderId = $metadata['order_id'] ?? null;

        if (is_scalar($orderId) && ctype_digit((string) $orderId)) {
            $order = $this->orders->find((int) $orderId);

            if ($order instanceof Order) {
                return $order;
            }
        }

        $sessionId = $this->stringProperty($session, 'id');

        if (null !== $sessionId) {
            return $this->orders->findOneBy(['stripeCheckoutSessionId' => $sessionId]);
        }

        $orderNumber = $metadata['order_number'] ?? $this->stringProperty($session, 'client_reference_id');

        if (is_scalar($orderNumber)) {
            return $this->orders->findOneBy(['orderNumber' => (string) $orderNumber]);
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function metadata(object $session): array
    {
        $metadata = $session->metadata ?? [];

        if (is_object($metadata) && method_exists($metadata, 'toArray')) {
            $metadata = $metadata->toArray();
        }

        if (!is_array($metadata)) {
            return [];
        }

        return $metadata;
    }

    private function customerEmail(object $session): ?string
    {
        $customerDetails = $session->customer_details ?? null;

        if (is_object($customerDetails)) {
            $email = $customerDetails->email ?? null;

            return is_scalar($email) && '' !== trim((string) $email) ? (string) $email : null;
        }

        $email = $session->customer_email ?? null;

        return is_scalar($email) && '' !== trim((string) $email) ? (string) $email : null;
    }

    private function stringProperty(object $object, string $property): ?string
    {
        $value = $object->{$property} ?? null;

        return is_scalar($value) && '' !== trim((string) $value) ? (string) $value : null;
    }

    private function objectIdProperty(object $object, string $property): ?string
    {
        $value = $object->{$property} ?? null;

        if (is_scalar($value) && '' !== trim((string) $value)) {
            return (string) $value;
        }

        if (is_object($value)) {
            $id = $value->id ?? null;

            return is_scalar($id) && '' !== trim((string) $id) ? (string) $id : null;
        }

        return null;
    }
}
