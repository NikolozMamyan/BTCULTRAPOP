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
        private OrderPaymentLinkSigner $paymentLinkSigner,
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->stripeConfig->isConfigured();
    }

    public function createSession(Order $order, ?string $idempotencyKey = null): Session
    {
        $stripe = $this->stripe();
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
            'success_url' => $this->checkoutSuccessUrl(),
            'cancel_url' => $this->paymentLinkSigner->checkoutCancelUrl($order),
        ];

        if (null !== $order->getCustomerEmail()) {
            $payload['customer_email'] = $order->getCustomerEmail();
        }

        if ($order->getDiscountAmountTaxIncludedCents() > 0) {
            $coupon = $stripe->coupons->create([
                'amount_off' => $order->getDiscountAmountTaxIncludedCents(),
                'currency' => strtolower($order->getCurrency()),
                'duration' => 'once',
                'name' => mb_substr(sprintf(
                    'ULTRAPOP %s',
                    $order->getPromoCodeSnapshot() ?? $order->getOrderNumber(),
                ), 0, 40),
                'metadata' => [
                    'order_number' => $order->getOrderNumber(),
                    'promo_code' => $order->getPromoCodeSnapshot() ?? '',
                ],
            ]);
            $payload['discounts'] = [['coupon' => $coupon->id]];
        }

        $requestOptions = null === $idempotencyKey
            ? []
            : ['idempotency_key' => $idempotencyKey];

        return $stripe->checkout->sessions->create($payload, $requestOptions);
    }

    public function retrieveSession(string $sessionId): Session
    {
        return $this->stripe()->checkout->sessions->retrieve($sessionId, []);
    }

    public function expireSession(string $sessionId): Session
    {
        return $this->stripe()->checkout->sessions->expire($sessionId, []);
    }

    private function checkoutSuccessUrl(): string
    {
        $successUrl = $this->urlGenerator->generate(
            'app_checkout_success',
            [],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        return $successUrl . (str_contains($successUrl, '?') ? '&' : '?') . 'session_id={CHECKOUT_SESSION_ID}';
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
        $productData = [
            'name' => $this->translator->trans('checkout.shipping_line'),
        ];
        $image = $this->assetUrlResolver->resolveAbsolute('img/checkout/shipping-truck.svg');

        if (null !== $image && str_starts_with($image, 'https://')) {
            $productData['images'] = [$image];
        }

        return [
            'price_data' => [
                'currency' => strtolower($order->getCurrency()),
                'product_data' => $productData,
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
