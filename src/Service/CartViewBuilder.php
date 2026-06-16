<?php

namespace App\Service;

use App\Entity\Cart;
use App\Entity\CartItem;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class CartViewBuilder
{
    private const FREE_SHIPPING_THRESHOLD_CENTS = 4900;

    public function __construct(
        private TranslatorInterface $translator,
        private UrlGeneratorInterface $urlGenerator,
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

        $subtotal = $cart->getTotalTaxIncludedCents();

        return [
            'items' => array_map(fn (CartItem $item): array => $this->item($item), $cart->getItems()->toArray()),
            'totalQuantity' => $cart->getTotalQuantity(),
            'subtotalCents' => $subtotal,
            'subtotalFormatted' => $this->formatCents($subtotal),
            'totalCents' => $subtotal,
            'totalFormatted' => $this->formatCents($subtotal),
            'shippingProgress' => min(100, (int) round(($subtotal / self::FREE_SHIPPING_THRESHOLD_CENTS) * 100)),
            'shippingMessage' => $this->shippingMessage($subtotal),
            'empty' => 0 === $cart->getItems()->count(),
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
            'totalCents' => 0,
            'totalFormatted' => $this->formatCents(0),
            'shippingProgress' => 0,
            'shippingMessage' => $this->translator->trans('overlay.shipping_empty'),
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

    private function shippingMessage(int $subtotalCents): string
    {
        $remaining = self::FREE_SHIPPING_THRESHOLD_CENTS - $subtotalCents;

        if ($remaining <= 0) {
            return $this->translator->trans('cart.shipping.free_unlocked');
        }

        return $this->translator->trans('cart.shipping.remaining', [
            '%amount%' => $this->formatCents($remaining),
        ]);
    }

    private function formatCents(int $cents): string
    {
        return number_format($cents / 100, 2, ',', ' ') . ' €';
    }
}
