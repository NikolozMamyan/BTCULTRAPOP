<?php

namespace App\Service;

use App\Entity\Cart;
use App\Entity\CartItem;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class CartViewBuilder
{
    public function __construct(
        private TranslatorInterface $translator,
        private UrlGeneratorInterface $urlGenerator,
        private ShippingRateCalculator $shippingRateCalculator,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function build(?Cart $cart): array
    {
        if (!$cart instanceof Cart) {
            return $this->empty();
        }

        if (0 === $cart->getItems()->count()) {
            return $this->empty();
        }

        $subtotal = $cart->getTotalTaxIncludedCents();
        $shipping = $this->shippingRateCalculator->quote($subtotal);
        $shippingAmount = $shipping['amountCents'];

        return [
            'items' => array_map(fn (CartItem $item): array => $this->item($item), $cart->getItems()->toArray()),
            'totalQuantity' => $cart->getTotalQuantity(),
            'subtotalCents' => $subtotal,
            'subtotalFormatted' => $this->formatCents($subtotal),
            'shippingAmountCents' => $shippingAmount,
            'shippingAmountFormatted' => $this->formatCents($shippingAmount),
            'shippingDisplay' => $shipping['free']
                ? $this->translator->trans('overlay.free')
                : $this->formatCents($shippingAmount),
            'shippingFree' => $shipping['free'],
            'totalCents' => $subtotal + $shippingAmount,
            'totalFormatted' => $this->formatCents($subtotal + $shippingAmount),
            'shippingProgress' => $shipping['progress'],
            'shippingMessage' => $this->shippingMessage($shipping),
            'shippingCheckpoints' => $this->shippingCheckpoints($shipping),
            'empty' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function empty(): array
    {
        return [
            'items' => [],
            'totalQuantity' => 0,
            'subtotalCents' => 0,
            'subtotalFormatted' => $this->formatCents(0),
            'shippingAmountCents' => 0,
            'shippingAmountFormatted' => $this->formatCents(0),
            'shippingDisplay' => '—',
            'shippingFree' => false,
            'totalCents' => 0,
            'totalFormatted' => $this->formatCents(0),
            'shippingProgress' => 0,
            'shippingMessage' => $this->translator->trans('overlay.shipping_empty'),
            'shippingCheckpoints' => $this->shippingCheckpoints($this->shippingRateCalculator->quote(0), false),
            'empty' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function item(CartItem $item): array
    {
        $product = $item->getProduct();
        $productId = $product?->getId();

        return [
            'id' => $item->getId(),
            'productId' => $productId,
            'name' => $product?->getName() ?? '',
            'image' => $product?->getCoverImage()?->getPath(),
            'quantity' => $item->getQuantity(),
            'unitPriceFormatted' => $this->formatCents($item->getUnitPriceTaxIncludedCents()),
            'totalFormatted' => $this->formatCents($item->getTotalTaxIncludedCents()),
            'productUrl' => null === $productId ? null : $this->urlGenerator->generate('app_front_product', ['id' => $productId]),
            'updateUrl' => $this->urlGenerator->generate('app_api_cart_item_update', ['id' => $item->getId()]),
            'removeUrl' => $this->urlGenerator->generate('app_api_cart_item_remove', ['id' => $item->getId()]),
        ];
    }

    /**
     * @param array{
     *     amountCents: int,
     *     nextShippingAmountCents: ?int,
     *     remainingToNextCents: int,
     *     free: bool
     * } $shipping
     */
    private function shippingMessage(array $shipping): string
    {
        if ($shipping['free']) {
            return $this->translator->trans('cart.shipping.free_unlocked');
        }

        if (0 === $shipping['nextShippingAmountCents']) {
            return $this->translator->trans('cart.shipping.next_free', [
                '%amount%' => $this->formatCents($shipping['remainingToNextCents']),
            ]);
        }

        return $this->translator->trans('cart.shipping.next_tier', [
            '%amount%' => $this->formatCents($shipping['remainingToNextCents']),
            '%next%' => $this->formatCents((int) $shipping['nextShippingAmountCents']),
        ]);
    }

    /**
     * @param array{checkpoints: list<array<string, int|bool>>} $shipping
     *
     * @return list<array<string, int|string|bool>>
     */
    private function shippingCheckpoints(array $shipping, bool $allowReached = true): array
    {
        return array_map(
            fn (array $checkpoint): array => [
                ...$checkpoint,
                'thresholdFormatted' => $this->formatWholeEuros((int) $checkpoint['thresholdCents']),
                'shippingAmountFormatted' => 0 === $checkpoint['shippingAmountCents']
                    ? $this->translator->trans('overlay.free')
                    : $this->formatCompactCents((int) $checkpoint['shippingAmountCents']),
                'reached' => $allowReached && (bool) $checkpoint['reached'],
                'current' => $allowReached && (bool) $checkpoint['current'],
            ],
            $shipping['checkpoints'],
        );
    }

    private function formatCents(int $cents): string
    {
        return number_format($cents / 100, 2, ',', ' ') . ' €';
    }

    private function formatCompactCents(int $cents): string
    {
        $decimals = 0 === $cents % 100 ? 0 : 2;

        return number_format($cents / 100, $decimals, ',', ' ') . ' €';
    }

    private function formatWholeEuros(int $cents): string
    {
        return number_format($cents / 100, 0, ',', ' ') . ' €';
    }
}
