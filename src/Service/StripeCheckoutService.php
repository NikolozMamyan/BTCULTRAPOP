<?php

namespace App\Service;

use App\Entity\Order;
use App\Entity\OrderItem;
use Stripe\Checkout\Session;
use Stripe\StripeClient;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class StripeCheckoutService
{
    public function __construct(
        private StripeConfigProvider $stripeConfig,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->stripeConfig->isConfigured();
    }

    public function createSession(Order $order): Session
    {
        $payload = [
            'mode' => 'payment',
            'payment_method_types' => ['card'],
            'line_items' => array_map(
                fn (OrderItem $item): array => $this->lineItem($item),
                $order->getItems()->toArray(),
            ),
            'client_reference_id' => $order->getOrderNumber(),
            'metadata' => [
                'order_id' => (string) $order->getId(),
                'order_number' => $order->getOrderNumber(),
            ],
            'success_url' => $this->urlGenerator->generate(
                'app_checkout_success',
                ['session_id' => '{CHECKOUT_SESSION_ID}'],
                UrlGeneratorInterface::ABSOLUTE_URL,
            ),
            'cancel_url' => $this->urlGenerator->generate(
                'app_checkout_cancel',
                ['order' => $order->getOrderNumber()],
                UrlGeneratorInterface::ABSOLUTE_URL,
            ),
        ];

        if (null !== $order->getCustomerEmail()) {
            $payload['customer_email'] = $order->getCustomerEmail();
        }

        return $this->stripe()->checkout->sessions->create($payload);
    }

    public function retrieveSession(string $sessionId): Session
    {
        return $this->stripe()->checkout->sessions->retrieve($sessionId, []);
    }

    /**
     * @return array<string, mixed>
     */
    private function lineItem(OrderItem $item): array
    {
        $productData = [
            'name' => $item->getProductName(),
        ];
        $image = $item->getProductImage();

        if (null !== $image && str_starts_with($image, 'http')) {
            $productData['images'] = [$image];
        }

        return [
            'price_data' => [
                'currency' => strtolower($item->getOrder()?->getCurrency() ?? 'EUR'),
                'product_data' => $productData,
                'unit_amount' => $item->getUnitPriceTaxIncludedCents(),
            ],
            'quantity' => $item->getQuantity(),
        ];
    }

    private function stripe(): StripeClient
    {
        return new StripeClient($this->stripeConfig->secretKey());
    }
}
