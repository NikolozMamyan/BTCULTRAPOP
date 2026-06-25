<?php

namespace App\Service;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\User;
use App\Enum\OrderStatus;
use App\Repository\OrderRepository;

final readonly class ProfileOrderProvider
{
    private const FALLBACK_IMAGE = 'img/products/fr-default-large_default.jpg';

    public function __construct(
        private OrderRepository $orders,
        private AssetUrlResolver $assetUrlResolver,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forUser(User $user, int $limit = 20): array
    {
        return array_map(
            fn (Order $order): array => $this->present($order),
            $this->orders->findRecentForUser($user, $limit),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Order $order): array
    {
        $items = array_map(
            fn (OrderItem $item): array => [
                'name' => $item->getProductName(),
                'reference' => $item->getProductReference(),
                'image' => $this->assetUrlResolver->resolve($item->getProductImage() ?: self::FALLBACK_IMAGE),
                'quantity' => $item->getQuantity(),
                'unit_price' => $this->formatCents($item->getUnitPriceTaxIncludedCents()),
                'total' => $this->formatCents($item->getTotalTaxIncludedCents()),
            ],
            $order->getItems()->toArray(),
        );

        return [
            'number' => $order->getOrderNumber(),
            'created_at' => $order->getCreatedAt(),
            'status' => $order->getStatus()->value,
            'status_key' => 'admin.order.status.' . $order->getStatus()->value,
            'status_tone' => $this->statusTone($order->getStatus()),
            'payment_status_key' => 'admin.order.payment_status.' . $order->getPaymentStatus()->value,
            'total' => $this->formatCents($order->getTotalTaxIncludedCents()),
            'shipping_amount' => $this->formatCents($order->getShippingAmountTaxIncludedCents()),
            'discount' => $this->formatCents($order->getDiscountAmountTaxIncludedCents()),
            'discount_cents' => $order->getDiscountAmountTaxIncludedCents(),
            'loyalty_points' => $order->getLoyaltyPointsEarned(),
            'shipping' => [
                'name' => $order->getShippingName(),
                'street' => $order->getShippingStreet(),
                'postal_code' => $order->getShippingPostalCode(),
                'city' => $order->getShippingCity(),
                'country_code' => $order->getShippingCountryCode(),
            ],
            'items' => $items,
            'items_count' => count($items),
            'total_quantity' => array_sum(array_column($items, 'quantity')),
        ];
    }

    private function statusTone(OrderStatus $status): string
    {
        return match ($status) {
            OrderStatus::PAID, OrderStatus::DELIVERED => 'green',
            OrderStatus::SHIPPED => 'blue',
            OrderStatus::PREPARATION => 'yellow',
            OrderStatus::CANCELLED, OrderStatus::REFUNDED => 'red',
            OrderStatus::PENDING_PAYMENT => 'gray',
        };
    }

    private function formatCents(int $cents): string
    {
        return number_format($cents / 100, 2, ',', ' ') . ' €';
    }
}
