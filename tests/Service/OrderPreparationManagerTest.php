<?php

namespace App\Tests\Service;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Product;
use App\Service\AdminSageOrderManager;
use App\Service\OrderPreparationManager;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class OrderPreparationManagerTest extends TestCase
{
    public function testItTracksScannedAndManuallyAdjustedQuantities(): void
    {
        $order = (new Order())
            ->setOrderNumber('UP-20261001-1')
            ->markPaid();
        $item = (new OrderItem())
            ->setProductName('Figurine test')
            ->setProductEan('3760000000000')
            ->setQuantity(2);
        $order->addItem($item);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::exactly(3))->method('flush');
        $sageOrders = (new \ReflectionClass(AdminSageOrderManager::class))->newInstanceWithoutConstructor();
        \assert($sageOrders instanceof AdminSageOrderManager);
        $manager = new OrderPreparationManager($entityManager, $sageOrders);

        $firstScan = $manager->scan($order, '3760000000000');
        self::assertSame(1, $firstScan['item']['prepared_quantity']);
        self::assertFalse($firstScan['preparation']['complete']);

        $secondScan = $manager->scan($order, '3760000000000');
        self::assertSame(2, $secondScan['item']['prepared_quantity']);
        self::assertTrue($secondScan['preparation']['complete']);
        self::assertSame(100, $secondScan['preparation']['progress']);

        $adjusted = $manager->updateQuantity($order, $item, 1);
        self::assertSame(1, $adjusted['item']['prepared_quantity']);
        self::assertSame(1, $adjusted['preparation']['remaining_quantity']);
        self::assertFalse($adjusted['preparation']['complete']);
    }

    public function testItRejectsAProductOutsideTheOrder(): void
    {
        $order = (new Order())
            ->setOrderNumber('UP-20261001-2')
            ->markPaid()
            ->addItem((new OrderItem())
                ->setProductName('Figurine test')
                ->setProductEan('3760000000000'));
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $sageOrders = (new \ReflectionClass(AdminSageOrderManager::class))->newInstanceWithoutConstructor();
        \assert($sageOrders instanceof AdminSageOrderManager);
        $manager = new OrderPreparationManager($entityManager, $sageOrders);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('admin.order.preparation.error.product_not_expected');

        $manager->scan($order, '3769999999999');
    }

    public function testItUsesTheCurrentProductEanWhenTheOrderSnapshotIsEmpty(): void
    {
        $product = (new Product())
            ->setName('Bonbons Naruto')
            ->setReference('51269')
            ->setEan('3770030630269');
        $item = (new OrderItem())
            ->setProduct($product)
            ->setProductName('Bonbons Naruto')
            ->setProductReference('51269')
            ->setProductEan(null);
        $order = (new Order())
            ->setOrderNumber('UP-20261001-3')
            ->markPaid()
            ->addItem($item);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('flush');
        $sageOrders = (new \ReflectionClass(AdminSageOrderManager::class))->newInstanceWithoutConstructor();
        \assert($sageOrders instanceof AdminSageOrderManager);
        $manager = new OrderPreparationManager($entityManager, $sageOrders);

        $view = $manager->view($order);
        self::assertSame('3770030630269', $view['items'][0]['ean']);

        $result = $manager->scan($order, '3770030630269');
        self::assertSame(1, $result['item']['prepared_quantity']);
        self::assertTrue($result['preparation']['complete']);
    }
}
