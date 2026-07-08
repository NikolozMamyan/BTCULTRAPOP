<?php

namespace App\Service;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\SageOrderExport;
use App\Enum\PaymentStatus;
use App\Exception\SageApiException;
use App\Repository\OrderRepository;
use App\Repository\SageOrderExportRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class AdminSageOrderManager
{
    private const SAGE_CUSTOMER_NUMBER = '9BTOC';
    private const DEFAULT_STATUS = 'Saisi';
    private const DEFAULT_SHIPPING_MODE = 'DDP';
    private const DEFAULT_DELIVERY_CONDITION = '';
    private const DEFAULT_PAYMENT_MODEL = '';
    private const DEFAULT_OWNER_FIRST_NAME = 'Savinien';
    private const DEFAULT_OWNER_NAME = 'SAINT-PAUL';

    public function __construct(
        private OrderRepository $orders,
        private SageOrderExportRepository $exports,
        private SageApiClient $sageApi,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return array{
     *     orders: list<array<string, mixed>>,
     *     stats: list<array{label: string, value: string, icon: string, tone: string}>
     * }
     */
    public function index(): array
    {
        $orders = $this->orders->findForAdmin(paymentStatus: PaymentStatus::PAID->value);
        $exports = $this->exports->findIndexedByOrderIds(array_values(array_filter(
            array_map(static fn (Order $order): ?int => $order->getId(), $orders),
        )));
        $presentedOrders = array_map(
            fn (Order $order): array => $this->presentOrder($order, $exports[(int) $order->getId()] ?? null),
            $orders,
        );
        $sentCount = count(array_filter($presentedOrders, static fn (array $order): bool => (bool) $order['exported']));

        return [
            'orders' => $presentedOrders,
            'stats' => [
                [
                    'label' => 'admin.sage_order.stats.paid_orders',
                    'value' => (string) count($presentedOrders),
                    'icon' => 'fa-solid fa-circle-check',
                    'tone' => 'green',
                ],
                [
                    'label' => 'admin.sage_order.stats.sent',
                    'value' => (string) $sentCount,
                    'icon' => 'fa-solid fa-paper-plane',
                    'tone' => 'blue',
                ],
                [
                    'label' => 'admin.sage_order.stats.pending',
                    'value' => (string) max(0, count($presentedOrders) - $sentCount),
                    'icon' => 'fa-solid fa-clock',
                    'tone' => 'yellow',
                ],
                [
                    'label' => 'admin.sage_order.stats.customer',
                    'value' => self::SAGE_CUSTOMER_NUMBER,
                    'icon' => 'fa-solid fa-id-badge',
                    'tone' => 'red',
                ],
            ],
        ];
    }

    public function export(Order $order): SageOrderExport
    {
        if (PaymentStatus::PAID !== $order->getPaymentStatus()) {
            throw new SageApiException('admin.sage_order.error.order_not_paid');
        }

        $existingExport = $this->exports->findOneBy(['customerOrder' => $order]);

        if ($existingExport instanceof SageOrderExport) {
            throw new SageApiException('admin.sage_order.error.already_sent');
        }

        $payload = $this->payload($order);
       

        $response = $this->sageApi->createOrder($payload);
        $export = (new SageOrderExport())
            ->setOrder($order)
            ->setSageStatusCode($response['status'])
            ->setPayload(json_encode($payload, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES))
            ->setSageResponse($this->responseToString($response['body']))
            ->setSentAt(new \DateTimeImmutable());

        $this->entityManager->persist($export);
        $this->entityManager->flush();

        return $export;
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(Order $order): array
    {
        $orderLines = array_values(array_map(
            fn (OrderItem $item): array => [
                'reference' => $this->lineReference($item),
                'designation' => $item->getProductName(),
                'prixHT' => $this->centsToAmount($item->getUnitPriceTaxExcludedCents()),
                'quantite' => $item->getQuantity(),
                'quantitePreparee' => $item->getQuantity(),
            ],
            array_filter(
                $order->getItems()->toArray(),
                static fn (OrderItem $item): bool => $item->getQuantity() > 0,
            ),
        ));

        if ([] === $orderLines) {
            throw new SageApiException('admin.sage_order.error.empty_order_lines');
        }

        $orderLines[] = [
            'reference' => 'ZTRANS',
            'designation' => 'Transport Cost',
            'prixHT' => $this->centsToAmount($order->getShippingAmountTaxIncludedCents()),
            'quantite' => 1,
            'quantitePreparee' => 1,
        ];

        $orderDate = $order->getCreatedAt();
        $deliveryDate = ($order->getPaidAt() ?? $orderDate)->modify('+2 days');

        return [
            'numClient' => self::SAGE_CUSTOMER_NUMBER,
            'dateCommande' => $this->dateForSage($orderDate),
            'dateLivraison' => $this->dateForSage($deliveryDate),
            'referenceCommande' => $order->getOrderNumber(),
            'statut' => self::DEFAULT_STATUS,
            'modeExpedition' => self::DEFAULT_SHIPPING_MODE,
            'condLivraison' => self::DEFAULT_DELIVERY_CONDITION,
            'modeleReglement' => self::DEFAULT_PAYMENT_MODEL,
            'ownerFirstName' => self::DEFAULT_OWNER_FIRST_NAME,
            'ownerName' => self::DEFAULT_OWNER_NAME,
            'instructionDeLivraison' => $this->shippingInstruction($order),
            'champsLibres' => [
                'additionalProp1' => '',
                'additionalProp2' => '',
                'additionalProp3' => '',
            ],
            'orderLines' => $orderLines,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentOrder(Order $order, ?SageOrderExport $export): array
    {
        return [
            'id' => $order->getId(),
            'number' => $order->getOrderNumber(),
            'customer' => $order->getCustomerName(),
            'email' => $order->getCustomerEmail(),
            'created_at' => $order->getCreatedAt(),
            'paid_at' => $order->getPaidAt(),
            'total' => $this->formatCents($order->getTotalTaxIncludedCents()),
            'items_count' => $order->getItems()->count(),
            'exported' => $export instanceof SageOrderExport,
            'sent_at' => $export?->getSentAt(),
            'sage_status_code' => $export?->getSageStatusCode(),
        ];
    }

    private function lineReference(OrderItem $item): string
    {
        $reference = trim((string) $item->getProductReference());

        if ('' !== $reference) {
            return $reference;
        }

        $ean = trim((string) $item->getProductEan());

        if ('' !== $ean) {
            return $ean;
        }

        return 'ORDERITEM-' . (string) $item->getId();
    }

    private function shippingInstruction(Order $order): string
    {
        $lines = array_filter([
            $order->getShippingName(),
            $order->getShippingStreet(),
            trim(sprintf('%s %s', $order->getShippingPostalCode(), $order->getShippingCity())),
            $order->getShippingCountryCode(),
            $order->getShippingPhone() ? 'Tel: ' . $order->getShippingPhone() : '',
        ]);

        return implode("\n", $lines);
    }

    private function dateForSage(\DateTimeImmutable $date): string
    {
        return $date->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.v\Z');
    }

    private function centsToAmount(int $cents): float
    {
        return round($cents / 100, 6);
    }

    private function responseToString(mixed $response): ?string
    {
        if (null === $response) {
            return null;
        }

        if (is_string($response)) {
            return $response;
        }

        return json_encode($response, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
    }

    private function formatCents(int $cents): string
    {
        return number_format($cents / 100, 2, ',', ' ') . ' €';
    }
}
