<?php

namespace App\Service;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Enum\OrderStatus;
use App\Enum\PaymentStatus;
use App\Repository\OrderRepository;

final readonly class AdminOrderProvider
{
    public function __construct(private OrderRepository $orders)
    {
    }

    /**
     * @return array{
     *     orders: list<array<string, mixed>>,
     *     stats: list<array{label: string, value: string, icon: string, tone: string}>,
     *     statuses: list<OrderStatus>,
     *     payment_statuses: list<PaymentStatus>
     * }
     */
    public function index(string $search = '', string $status = '', string $paymentStatus = ''): array
    {
        return [
            'orders' => array_map(
                fn (Order $order): array => $this->presentListOrder($order),
                $this->orders->findForAdmin($search, $status, $paymentStatus),
            ),
            'stats' => [
                [
                    'label' => 'admin.order.stats.total',
                    'value' => (string) $this->orders->count([]),
                    'icon' => 'fa-solid fa-receipt',
                    'tone' => 'blue',
                ],
                [
                    'label' => 'admin.order.stats.pending',
                    'value' => (string) $this->orders->count(['status' => OrderStatus::PENDING_PAYMENT]),
                    'icon' => 'fa-solid fa-hourglass-half',
                    'tone' => 'yellow',
                ],
                [
                    'label' => 'admin.order.stats.paid',
                    'value' => (string) $this->orders->count(['paymentStatus' => PaymentStatus::PAID]),
                    'icon' => 'fa-solid fa-circle-check',
                    'tone' => 'green',
                ],
                [
                    'label' => 'admin.order.stats.revenue',
                    'value' => $this->formatCents($this->orders->paidRevenueCents()),
                    'icon' => 'fa-solid fa-chart-line',
                    'tone' => 'red',
                ],
            ],
            'statuses' => OrderStatus::cases(),
            'payment_statuses' => PaymentStatus::cases(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function show(Order $order): array
    {
        $items = array_map(
            fn (OrderItem $item): array => $this->presentItem($item),
            $order->getItems()->toArray(),
        );

        return [
            ...$this->presentListOrder($order),
            'subtotal_tax_excluded' => $this->formatCents($order->getTotalTaxExcludedCents()),
            'shipping_amount' => $this->formatCents($order->getShippingAmountTaxIncludedCents()),
            'discount' => $this->formatCents($order->getDiscountAmountTaxIncludedCents()),
            'loyalty_points' => $order->getLoyaltyPointsEarned(),
            'paid_at' => $order->getPaidAt(),
            'cancelled_at' => $order->getCancelledAt(),
            'stripe_checkout_session_id' => $order->getStripeCheckoutSessionId(),
            'stripe_payment_intent_id' => $order->getStripePaymentIntentId(),
            'stripe_customer_id' => $order->getStripeCustomerId(),
            'payment_failure_reason' => $order->getPaymentFailureReason(),
            'shipping' => [
                'name' => $order->getShippingName(),
                'street' => $order->getShippingStreet(),
                'postal_code' => $order->getShippingPostalCode(),
                'city' => $order->getShippingCity(),
                'country_code' => $order->getShippingCountryCode(),
                'phone' => $order->getShippingPhone(),
            ],
            'items' => $items,
            'items_count' => count($items),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentListOrder(Order $order): array
    {
        return [
            'id' => $order->getId(),
            'number' => $order->getOrderNumber(),
            'customer' => $order->getCustomerName(),
            'email' => $order->getCustomerEmail(),
            'status' => $order->getStatus()->value,
            'payment_status' => $order->getPaymentStatus()->value,
            'status_key' => 'admin.order.status.' . $order->getStatus()->value,
            'payment_status_key' => 'admin.order.payment_status.' . $order->getPaymentStatus()->value,
            'total' => $this->formatCents($order->getTotalTaxIncludedCents()),
            'total_cents' => $order->getTotalTaxIncludedCents(),
            'created_at' => $order->getCreatedAt(),
            'updated_at' => $order->getUpdatedAt(),
            'user' => $order->getUser(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentItem(OrderItem $item): array
    {
        return [
            'name' => $item->getProductName(),
            'reference' => $item->getProductReference(),
            'ean' => $item->getProductEan(),
            'image' => $item->getProductImage(),
            'category' => $item->getCategoryName(),
            'license' => $item->getLicenseName(),
            'quantity' => $item->getQuantity(),
            'unit_price' => $this->formatCents($item->getUnitPriceTaxIncludedCents()),
            'total' => $this->formatCents($item->getTotalTaxIncludedCents()),
        ];
    }

    private function formatCents(int $cents): string
    {
        return number_format($cents / 100, 2, ',', ' ') . ' €';
    }
}
