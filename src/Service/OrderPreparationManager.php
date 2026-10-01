<?php

namespace App\Service;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Enum\OrderStatus;
use App\Enum\PaymentStatus;
use Doctrine\ORM\EntityManagerInterface;

final readonly class OrderPreparationManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AdminSageOrderManager $sageOrders,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function view(Order $order): array
    {
        if (PaymentStatus::PAID !== $order->getPaymentStatus()) {
            throw new \InvalidArgumentException('admin.order.preparation.error.not_paid');
        }

        $items = array_map(
            fn (OrderItem $item): array => $this->presentItem($item),
            $order->getItems()->toArray(),
        );
        $expected = array_sum(array_column($items, 'quantity'));
        $prepared = array_sum(array_column($items, 'prepared_quantity'));

        return [
            'id' => $order->getId(),
            'number' => $order->getOrderNumber(),
            'customer' => $order->getCustomerName(),
            'status' => $order->getStatus()->value,
            'items' => $items,
            'expected_quantity' => $expected,
            'prepared_quantity' => $prepared,
            'remaining_quantity' => max(0, $expected - $prepared),
            'progress' => $expected > 0 ? (int) floor(($prepared / $expected) * 100) : 0,
            'complete' => $expected > 0 && $prepared === $expected,
            'editable' => $this->isEditable($order),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function scan(Order $order, string $ean): array
    {
        $this->assertEditable($order);
        $ean = trim($ean);

        if (!preg_match('/^\d{8,13}$/', $ean)) {
            throw new \InvalidArgumentException('admin.order.preparation.error.invalid_code');
        }

        $matchingItems = array_values(array_filter(
            $order->getItems()->toArray(),
            fn (OrderItem $item): bool => $ean === $this->itemEan($item),
        ));

        if ([] === $matchingItems) {
            throw new \InvalidArgumentException('admin.order.preparation.error.product_not_expected');
        }

        $item = null;

        foreach ($matchingItems as $candidate) {
            if ($candidate->getPreparedQuantity() < $candidate->getQuantity()) {
                $item = $candidate;
                break;
            }
        }

        if (!$item instanceof OrderItem) {
            throw new \InvalidArgumentException('admin.order.preparation.error.already_complete');
        }

        $item->setPreparedQuantity($item->getPreparedQuantity() + 1);
        $this->entityManager->flush();

        return [
            'message' => 'admin.order.preparation.scan.success',
            'item' => $this->presentItem($item),
            'preparation' => $this->view($order),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function updateQuantity(Order $order, OrderItem $item, int $quantity): array
    {
        $this->assertEditable($order);

        if ($item->getOrder() !== $order) {
            throw new \InvalidArgumentException('admin.order.preparation.error.item_not_found');
        }

        if ($quantity < 0 || $quantity > $item->getQuantity()) {
            throw new \InvalidArgumentException('admin.order.preparation.error.invalid_quantity');
        }

        $item->setPreparedQuantity($quantity);
        $this->entityManager->flush();

        return [
            'item' => $this->presentItem($item),
            'preparation' => $this->view($order),
        ];
    }

    public function completeAndExport(Order $order): void
    {
        $this->assertEditable($order);
        $view = $this->view($order);

        if (!$view['complete']) {
            throw new \InvalidArgumentException('admin.order.preparation.error.incomplete');
        }

        $order->setStatus(OrderStatus::PREPARED);

        try {
            $this->sageOrders->export($order);
        } catch (\Throwable $exception) {
            $order->setStatus(OrderStatus::PREPARATION);

            throw $exception;
        }
    }

    private function assertEditable(Order $order): void
    {
        if (PaymentStatus::PAID !== $order->getPaymentStatus()) {
            throw new \InvalidArgumentException('admin.order.preparation.error.not_paid');
        }

        if (!$this->isEditable($order)) {
            throw new \InvalidArgumentException('admin.order.preparation.error.not_editable');
        }
    }

    private function isEditable(Order $order): bool
    {
        return in_array($order->getStatus(), [OrderStatus::PAID, OrderStatus::PREPARATION], true);
    }

    /**
     * @return array<string, mixed>
     */
    private function presentItem(OrderItem $item): array
    {
        return [
            'id' => $item->getId(),
            'name' => $item->getProductName(),
            'reference' => $item->getProductReference(),
            'ean' => $this->itemEan($item),
            'image' => $item->getProductImage(),
            'quantity' => $item->getQuantity(),
            'prepared_quantity' => $item->getPreparedQuantity(),
            'remaining_quantity' => max(0, $item->getQuantity() - $item->getPreparedQuantity()),
            'complete' => $item->getPreparedQuantity() === $item->getQuantity(),
        ];
    }

    private function itemEan(OrderItem $item): ?string
    {
        $ean = trim((string) $item->getProductEan());

        if ('' === $ean) {
            $ean = trim((string) $item->getProduct()?->getEan());
        }

        return '' === $ean ? null : $ean;
    }
}
