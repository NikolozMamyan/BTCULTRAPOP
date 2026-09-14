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
            ->setShippingAmountTaxExcludedCents(800)
            ->setShippingAmountTaxIncludedCents(960);
        $order->addItem(
            (new OrderItem())
                ->setProductName('Produit test')
                ->setProductReference('TEST-1')
                ->setQuantity(1)
                ->setUnitPriceTaxExcludedCents(585)
                ->setUnitPriceTaxIncludedCents(648),
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
        self::assertStringContainsString('Produit test x1 (TEST-1)', $content);
        self::assertStringContainsString('"Sous-total produits HT";"Livraison HT";"Remise HT";TVA;"Total TTC"', $content);
        self::assertStringContainsString('5,85;8,00;0,00;2,23;16,08', $content);
        self::assertSame('text/csv; charset=UTF-8', $response->headers->get('Content-Type'));
        self::assertStringContainsString('commandes-', (string) $response->headers->get('Content-Disposition'));
    }
}
