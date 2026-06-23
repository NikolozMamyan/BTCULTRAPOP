<?php

namespace App\Service;

use App\Entity\Order;
use App\Entity\OrderItem;
use Stripe\Checkout\Session;
use Stripe\StripeClient;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class StripeCheckoutService
{
    public function __construct(
        private StripeConfigProvider $stripeConfig,
        private UrlGeneratorInterface $urlGenerator,
        private TranslatorInterface $translator,
        private AssetUrlResolver $assetUrlResolver,
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->stripeConfig->isConfigured();
    }

    public function createSession(Order $order): Session
    {
        $lineItems = array_map(
            fn (OrderItem $item): array => $this->lineItem($item),
            $order->getItems()->toArray(),
        );

        if ($order->getShippingAmountTaxIncludedCents() > 0) {
            $lineItems[] = $this->shippingLineItem($order);
        }

        $payload = [
            'mode' => 'payment',
            'payment_method_types' => ['card'],
            'line_items' => $lineItems,
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
        $image = $this->assetUrlResolver->resolveAbsolute($item->getProductImage());

        if (null !== $image && str_starts_with($image, 'https://')) {
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

    /**
     * @return array<string, mixed>
     */
    private function shippingLineItem(Order $order): array
    {
        return [
            'price_data' => [
                'currency' => strtolower($order->getCurrency()),
                'product_data' => [
                    'name' => $this->translator->trans('checkout.shipping_line'),
                ],
                'unit_amount' => $order->getShippingAmountTaxIncludedCents(),
            ],
            'quantity' => 1,
        ];
    }

    private function stripe(): StripeClient
    {
        return new StripeClient($this->stripeConfig->secretKey());
    }
}
