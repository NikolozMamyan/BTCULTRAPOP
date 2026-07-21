<?php

namespace App\Service\Analytics;

use App\Entity\Cart;
use App\Entity\CartItem;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Product;
use App\Enum\PaymentStatus;
use App\Service\PromoCodeManager;

final readonly class EcommercePayloadBuilder
{
    private const BRAND = 'ULTRAPOP';
    private const CURRENCY = 'EUR';

    public function __construct(private PromoCodeManager $promoCodeManager)
    {
    }

    /**
     * @return array{currency: string, value: float, items: list<array<string, mixed>>}
     */
    public function viewItem(Product $product): array
    {
        $priceCents = $this->decimalToCents($product->getEffectivePriceTaxIncluded());

        return [
            'currency' => self::CURRENCY,
            'value' => $this->centsToEuros($priceCents),
            'items' => [
                $this->productItem($product, 1, $priceCents),
            ],
        ];
    }

    /**
     * @return array{currency: string, value: float, items: list<array<string, mixed>>}
     */
    public function addToCart(Product $product, int $quantity, int $unitPriceTaxIncludedCents): array
    {
        $quantity = max(1, $quantity);
        $unitPriceTaxIncludedCents = max(0, $unitPriceTaxIncludedCents);

        return [
            'currency' => self::CURRENCY,
            'value' => $this->centsToEuros($unitPriceTaxIncludedCents * $quantity),
            'items' => [
                $this->productItem($product, $quantity, $unitPriceTaxIncludedCents),
            ],
        ];
    }

    /**
     * @return array{currency: string, value: float, coupon?: string, items: list<array<string, mixed>>}|null
     */
    public function cart(Cart $cart): ?array
    {
        if (0 === $cart->getItems()->count()) {
            return null;
        }

        $items = array_values(array_map(
            fn (CartItem $item): array => $this->cartItem($item),
            $cart->getItems()->toArray(),
        ));

        if ([] === $items) {
            return null;
        }

        $discountCents = $this->promoCodeManager->discountForCart($cart);
        $productDiscountCents = $cart->getPromoCode()?->appliesToShipping() ? 0 : $discountCents;
        $payload = [
            'currency' => self::CURRENCY,
            'value' => $this->centsToEuros(max(0, $cart->getTotalTaxIncludedCents() - $productDiscountCents)),
            'items' => $items,
        ];

        $coupon = $this->cleanNullable($cart->getPromoCode()?->getCode());

        if (null !== $coupon) {
            $payload['coupon'] = $coupon;
        }

        return $payload;
    }

    /**
     * @return array{
     *     transaction_id: string,
     *     value: float,
     *     shipping: float,
     *     currency: string,
     *     coupon?: string,
     *     items: list<array<string, mixed>>
     * }|null
     */
    public function purchase(Order $order): ?array
    {
        if (PaymentStatus::PAID !== $order->getPaymentStatus()) {
            return null;
        }

        $transactionId = $this->clean($order->getOrderNumber());

        if ('' === $transactionId) {
            return null;
        }

        $items = array_values(array_map(
            fn (OrderItem $item): array => $this->orderItem($item),
            $order->getItems()->toArray(),
        ));

        if ([] === $items) {
            return null;
        }

        $itemsTotalCents = array_reduce(
            $order->getItems()->toArray(),
            static fn (int $total, OrderItem $item): int => $total + $item->getTotalTaxIncludedCents(),
            0,
        );
        $shippingDiscount = $order->getPromoCode()?->appliesToShipping() ?? false;
        $payload = [
            'transaction_id' => $transactionId,
            'value' => $this->centsToEuros(max(
                0,
                $itemsTotalCents - ($shippingDiscount ? 0 : $order->getDiscountAmountTaxIncludedCents()),
            )),
            'shipping' => $this->centsToEuros(max(
                0,
                $order->getShippingAmountTaxIncludedCents()
                    - ($shippingDiscount ? $order->getDiscountAmountTaxIncludedCents() : 0),
            )),
            'currency' => $this->clean($order->getCurrency()) ?: self::CURRENCY,
            'items' => $items,
        ];

        $coupon = $this->cleanNullable($order->getPromoCodeSnapshot());

        if (null !== $coupon) {
            $payload['coupon'] = $coupon;
        }

        return $payload;
    }

    /**
     * @return array{
     *     item_id: string,
     *     item_name: string,
     *     item_brand: string,
     *     item_category: string,
     *     item_category2: string,
     *     price: float,
     *     quantity: int
     * }
     */
    private function productItem(Product $product, int $quantity, int $unitPriceTaxIncludedCents): array
    {
        return [
            'item_id' => $this->productIdentifier($product),
            'item_name' => $this->clean($product->getName()),
            'item_brand' => self::BRAND,
            'item_category' => $this->clean($product->getCategory()?->getName()),
            'item_category2' => $this->clean($product->getLicense()?->getName()),
            'price' => $this->centsToEuros($unitPriceTaxIncludedCents),
            'quantity' => max(1, $quantity),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function cartItem(CartItem $item): array
    {
        $product = $item->getProduct();

        if ($product instanceof Product) {
            return $this->productItem(
                $product,
                $item->getQuantity(),
                $item->getUnitPriceTaxIncludedCents(),
            );
        }

        return [
            'item_id' => $this->cartItemFallbackIdentifier($item),
            'item_name' => '',
            'item_brand' => self::BRAND,
            'item_category' => '',
            'item_category2' => '',
            'price' => $this->centsToEuros($item->getUnitPriceTaxIncludedCents()),
            'quantity' => max(1, $item->getQuantity()),
        ];
    }

    /**
     * @return array{
     *     item_id: string,
     *     item_name: string,
     *     item_brand: string,
     *     item_category: string,
     *     item_category2: string,
     *     price: float,
     *     quantity: int
     * }
     */
    private function orderItem(OrderItem $item): array
    {
        return [
            'item_id' => $this->orderItemIdentifier($item),
            'item_name' => $this->clean($item->getProductName()),
            'item_brand' => self::BRAND,
            'item_category' => $this->clean($item->getCategoryName()),
            'item_category2' => $this->clean($item->getLicenseName()),
            'price' => $this->centsToEuros($item->getUnitPriceTaxIncludedCents()),
            'quantity' => max(1, $item->getQuantity()),
        ];
    }

    private function productIdentifier(Product $product): string
    {
        $reference = $this->cleanNullable($product->getReference());

        if (null !== $reference) {
            return $reference;
        }

        $ean = $this->cleanNullable($product->getEan());

        if (null !== $ean) {
            return $ean;
        }

        if (null !== $product->getId()) {
            return (string) $product->getId();
        }

        return 'product:' . substr(sha1($product->getName()), 0, 16);
    }

    private function orderItemIdentifier(OrderItem $item): string
    {
        $reference = $this->cleanNullable($item->getProductReference());

        if (null !== $reference) {
            return $reference;
        }

        $ean = $this->cleanNullable($item->getProductEan());

        if (null !== $ean) {
            return $ean;
        }

        $productId = $item->getProduct()?->getId();

        if (null !== $productId) {
            return (string) $productId;
        }

        if (null !== $item->getId()) {
            return 'order_item:' . $item->getId();
        }

        return 'order_item:' . substr(sha1(implode('|', [
            $item->getProductName(),
            (string) $item->getUnitPriceTaxIncludedCents(),
            (string) $item->getQuantity(),
            $item->getCategoryName() ?? '',
            $item->getLicenseName() ?? '',
        ])), 0, 16);
    }

    private function cartItemFallbackIdentifier(CartItem $item): string
    {
        if (null !== $item->getId()) {
            return 'cart_item:' . $item->getId();
        }

        return 'cart_item:' . substr(sha1(sprintf(
            '%d|%d|%d',
            $item->getQuantity(),
            $item->getUnitPriceTaxIncludedCents(),
            $item->getUnitPriceTaxExcludedCents(),
        )), 0, 16);
    }

    private function decimalToCents(string $amount): int
    {
        $normalized = trim(str_replace(',', '.', $amount));

        if (!preg_match('/^\d+(?:\.\d+)?$/', $normalized)) {
            return 0;
        }

        [$units, $fraction] = array_pad(explode('.', $normalized, 2), 2, '');
        $fraction = str_pad(substr($fraction, 0, 3), 3, '0');
        $cents = ((int) $units * 100) + (int) substr($fraction, 0, 2);

        if ((int) $fraction[2] >= 5) {
            ++$cents;
        }

        return $cents;
    }

    private function centsToEuros(int $cents): float
    {
        return round(max(0, $cents) / 100, 2);
    }

    private function clean(?string $value): string
    {
        return trim((string) $value);
    }

    private function cleanNullable(?string $value): ?string
    {
        $value = $this->clean($value);

        return '' === $value ? null : $value;
    }
}
