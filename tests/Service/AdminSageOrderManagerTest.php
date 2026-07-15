<?php

namespace App\Tests\Service;

use App\Entity\Category;
use App\Entity\License;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\PromoCode;
use App\Entity\Product;
use App\Entity\SageOrderExport;
use App\Service\AdminSageOrderManager;
use PHPUnit\Framework\TestCase;

final class AdminSageOrderManagerTest extends TestCase
{
    public function testItBuildsSagePayloadFromPaidOrderSnapshots(): void
    {
        $order = (new Order())
            ->setOrderNumber('UP-20260707-001')
            ->setCustomerName('Nina Uzumaki')
            ->setCustomerEmail('nina@example.test')
            ->setCurrency('EUR')
            ->setShippingName('Nina Uzumaki')
            ->setShippingStreet('13 quai Kléber')
            ->setShippingPostalCode('67000')
            ->setShippingCity('Strasbourg')
            ->setShippingCountryCode('FR')
            ->setShippingPhone('0102030405')
            ->setShippingAmountTaxIncludedCents(800);
        $order->markPaid(new \DateTimeImmutable('2026-07-07 14:00:00', new \DateTimeZone('Europe/Paris')));
        $order->addItem(
            (new OrderItem())
                ->setProduct($this->product())
                ->setProductName('ULTRAPOP - Naruto - Tropical 33cl')
                ->setProductReference('28989')
                ->setProductEan('3770027194989')
                ->setQuantity(2)
                ->setUnitPriceTaxExcludedCents(108)
                ->setUnitPriceTaxIncludedCents(131),
        );
        $order->refreshTotals();

        $manager = (new \ReflectionClass(AdminSageOrderManager::class))->newInstanceWithoutConstructor();
        \assert($manager instanceof AdminSageOrderManager);

        $payload = $manager->payload($order);

        self::assertSame('9BTOC', $payload['numClient']);
        self::assertSame('UP-20260707-001', $payload['referenceCommande']);
        self::assertSame('Saisi', $payload['statut']);
        self::assertSame('DDP', $payload['modeExpedition']);
        self::assertSame('', $payload['modeleReglement']);
        self::assertSame('Savinien', $payload['ownerFirstName']);
        self::assertSame('SAINT-PAUL', $payload['ownerName']);
        self::assertStringContainsString('13 quai Kléber', $payload['instructionDeLivraison']);
        self::assertSame([
            'additionalProp1' => '',
            'additionalProp2' => '',
            'additionalProp3' => '',
        ], $payload['champsLibres']);
        self::assertMatchesRegularExpression('/^\\d{4}-\\d{2}-\\d{2}T\\d{2}:\\d{2}:\\d{2}\\.\\d{3}Z$/', $payload['dateCommande']);
        self::assertCount(2, $payload['orderLines']);
        self::assertSame([
            'reference' => '28989',
            'designation' => 'ULTRAPOP - Naruto - Tropical 33cl',
            'prixHT' => 1.08,
            'quantite' => 2,
            'quantitePreparee' => 2,
        ], $payload['orderLines'][0]);
        self::assertSame([
            'reference' => 'ZTRANS',
            'designation' => 'Transport Cost',
            'prixHT' => 8.0,
            'quantite' => 1,
            'quantitePreparee' => 1,
        ], $payload['orderLines'][1]);
    }

    public function testEntityStoresSuccessfulExportMetadata(): void
    {
        $order = (new Order())->setOrderNumber('UP-20260707-002');
        $export = (new SageOrderExport())
            ->setOrder($order)
            ->setSageStatusCode(200)
            ->setPayload('{"numClient":"9BTOC"}')
            ->setSageResponse('{"ok":true}');

        self::assertSame($order, $export->getOrder());
        self::assertSame(200, $export->getSageStatusCode());
        self::assertSame('{"numClient":"9BTOC"}', $export->getPayload());
        self::assertSame('{"ok":true}', $export->getSageResponse());
    }

    public function testItAddsDiscountLineWhenOrderHasPromoCodeDiscount(): void
    {
        $order = (new Order())
            ->setOrderNumber('UP-20260707-003')
            ->setDiscountAmountTaxIncludedCents(1000)
            ->setPromoCode((new PromoCode())->setCode('WELCOME10'));
        $order->markPaid(new \DateTimeImmutable('2026-07-07 14:00:00', new \DateTimeZone('Europe/Paris')));
        $order->addItem(
            (new OrderItem())
                ->setProduct($this->product())
                ->setProductName('ULTRAPOP - Naruto - Tropical 33cl')
                ->setProductReference('28989')
                ->setQuantity(1)
                ->setUnitPriceTaxExcludedCents(108)
                ->setUnitPriceTaxIncludedCents(131),
        );
        $order->refreshTotals();

        $manager = (new \ReflectionClass(AdminSageOrderManager::class))->newInstanceWithoutConstructor();
        \assert($manager instanceof AdminSageOrderManager);

        $payload = $manager->payload($order);

        self::assertCount(3, $payload['orderLines']);
        self::assertSame([
            'reference' => 'ZREMISE',
            'designation' => 'Remise WELCOME10',
            'prixHT' => 0,
            'quantite' => 1,
            'quantitePreparee' => 1,
            'tauxRemise' => '10F',
        ], $payload['orderLines'][1]);
        self::assertSame('ZTRANS', $payload['orderLines'][2]['reference']);
    }

    private function product(): Product
    {
        return (new Product())
            ->setName('ULTRAPOP - Naruto - Tropical 33cl')
            ->setReference('28989')
            ->setCategory((new Category())->setName('Jus'))
            ->setLicense((new License())->setName('Naruto'));
    }
}
