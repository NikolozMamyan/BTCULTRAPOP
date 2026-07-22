<?php

namespace App\Tests\Service;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Service\AdminOrderCsvExporter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\Translator;

final class AdminOrderCsvExporterTest extends TestCase
{
    public function testItExportsExcelCompatibleCsvAndProtectsFormulaCells(): void
    {
        $order = (new Order())
            ->setOrderNumber('UP-20260722-000001')
            ->setCustomerName('=DANGEROUS')
            ->setCustomerEmail('client@example.com')
            ->setShippingName('Client')
            ->setShippingStreet('10 rue Test')
            ->setShippingPostalCode('75001')
            ->setShippingCity('Paris')
            ->setShippingCountryCode('FR')
            ->setShippingPhone('06 12 34 56 78')
            ->setShippingAmountTaxIncludedCents(600);
        $order->addItem(
            (new OrderItem())
                ->setProductName('Produit test')
                ->setProductReference('TEST-1')
                ->setQuantity(2)
                ->setUnitPriceTaxExcludedCents(1000)
                ->setUnitPriceTaxIncludedCents(1200),
        );
        $order->refreshTotals();

        $response = (new AdminOrderCsvExporter(new Translator('fr')))->response([$order]);
        ob_start();
        $response->sendContent();
        $content = (string) ob_get_clean();

        self::assertStringStartsWith("\xEF\xBB\xBF", $content);
        self::assertStringContainsString('UP-20260722-000001', $content);
        self::assertStringContainsString("'=DANGEROUS", $content);
        self::assertStringContainsString('Email;Téléphone;Statut', $content);
        self::assertStringContainsString('06 12 34 56 78', $content);
        self::assertStringContainsString('Produit test x2 (TEST-1)', $content);
        self::assertStringContainsString('30,00', $content);
        self::assertSame('text/csv; charset=UTF-8', $response->headers->get('Content-Type'));
        self::assertStringContainsString('commandes-', (string) $response->headers->get('Content-Disposition'));
    }
}
