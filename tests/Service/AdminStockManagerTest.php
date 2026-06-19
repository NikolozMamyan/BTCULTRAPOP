<?php

namespace App\Tests\Service;

use App\Entity\Product;
use App\Service\AdminStockManager;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AdminStockManagerTest extends TestCase
{
    public function testItUpdatesAProductQuantity(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('flush');
        $manager = new AdminStockManager($entityManager);
        $product = (new Product())->setQuantity(3);

        $result = $manager->updateProduct($product, '18');

        self::assertSame(18, $product->getQuantity());
        self::assertSame(['id' => 0, 'quantity' => 18], $result);
    }

    #[DataProvider('invalidQuantityProvider')]
    public function testItRejectsAnInvalidQuantity(string $quantity): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('flush');
        $manager = new AdminStockManager($entityManager);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('admin.stock.error.invalid_quantity');

        $manager->updateProduct(new Product(), $quantity);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidQuantityProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'negative' => ['-1'];
        yield 'decimal' => ['1.5'];
        yield 'text' => ['five'];
        yield 'database overflow' => ['2147483648'];
    }
}
