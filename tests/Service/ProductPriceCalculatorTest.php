<?php

namespace App\Tests\Service;

use App\Service\ProductPriceCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ProductPriceCalculatorTest extends TestCase
{
    #[DataProvider('providePrices')]
    public function testTaxIncludedPriceIsCalculatedFromTaxExcludedPrice(
        string $taxExcluded,
        string $taxRate,
        string $expectedTaxIncluded,
    ): void {
        self::assertSame(
            $expectedTaxIncluded,
            (new ProductPriceCalculator())->taxIncluded($taxExcluded, $taxRate),
        );
    }

    public static function providePrices(): iterable
    {
        yield 'standard VAT' => ['10', '20', '12.000000'];
        yield 'food VAT with six decimals' => ['1.886256', '5.5', '1.990000'];
        yield 'comma input' => ['49,916667', '20,00', '59.900000'];
        yield 'zero amount' => ['0', '20', '0.000000'];
    }

    public function testTaxRateAboveOneHundredIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('admin.price.error.invalid_tax_rate');

        (new ProductPriceCalculator())->taxIncluded('10', '100.01');
    }

    public function testNegativeTaxRateIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('admin.price.error.invalid_tax_rate');

        (new ProductPriceCalculator())->taxIncluded('10', '-5.5');
    }
}
