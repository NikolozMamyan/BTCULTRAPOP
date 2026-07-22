<?php

namespace App\Service;

use App\Entity\Order;
use App\Entity\OrderItem;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class AdminOrderCsvExporter
{
    public function __construct(private TranslatorInterface $translator)
    {
    }

    /**
     * @param list<Order> $orders
     */
    public function response(array $orders): StreamedResponse
    {
        $orders = array_slice(array_values($orders), 0, 200);
        $response = new StreamedResponse(function () use ($orders): void {
            $output = fopen('php://output', 'wb');

            if (false === $output) {
                throw new \RuntimeException('Unable to open the CSV output stream.');
            }

            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, [
                'Numéro',
                'Date',
                'Client',
                'Email',
                'Téléphone',
                'Statut',
                'Paiement',
                'Adresse',
                'Code promo',
                'Produits',
                'Sous-total HT',
                'Livraison TTC',
                'Remise TTC',
                'Total TTC',
            ], ';', '"', '\\');

            foreach ($orders as $order) {
                fputcsv($output, array_map($this->csvSafe(...), [
                    $order->getOrderNumber(),
                    $order->getCreatedAt()->format('d/m/Y H:i:s'),
                    $order->getCustomerName(),
                    $order->getCustomerEmail() ?? '',
                    $order->getShippingPhone() ?? '',
                    $this->translator->trans('admin.order.status.' . $order->getStatus()->value),
                    $this->translator->trans('admin.order.payment_status.' . $order->getPaymentStatus()->value),
                    sprintf(
                        '%s, %s %s, %s',
                        $order->getShippingStreet(),
                        $order->getShippingPostalCode(),
                        $order->getShippingCity(),
                        $order->getShippingCountryCode(),
                    ),
                    $order->getPromoCodeSnapshot() ?? '',
                    implode(' | ', array_map(
                        static fn (OrderItem $item): string => sprintf(
                            '%s x%d (%s)',
                            $item->getProductName(),
                            $item->getQuantity(),
                            $item->getProductReference() ?? '-',
                        ),
                        $order->getItems()->toArray(),
                    )),
                    $this->amount($order->getTotalTaxExcludedCents()),
                    $this->amount($order->getShippingAmountTaxIncludedCents()),
                    $this->amount($order->getDiscountAmountTaxIncludedCents()),
                    $this->amount($order->getTotalTaxIncludedCents()),
                ]), ';', '"', '\\');
            }

            fclose($output);
        });

        $filename = sprintf('commandes-%s.csv', (new \DateTimeImmutable())->format('Ymd-His'));
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set(
            'Content-Disposition',
            HeaderUtils::makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $filename),
        );

        return $response;
    }

    private function amount(int $cents): string
    {
        return number_format($cents / 100, 2, ',', '');
    }

    private function csvSafe(mixed $value): string
    {
        $value = (string) $value;

        return preg_match('/^[=+\-@]/', ltrim($value)) ? "'" . $value : $value;
    }
}
