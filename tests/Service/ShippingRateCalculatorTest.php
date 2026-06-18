<?php

namespace App\Tests\Service;

use App\Service\ShippingRateCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ShippingRateCalculatorTest extends TestCase
{
    #[DataProvider('shippingTiers')]
    public function testShippingAmountFollowsCartSubtotalTiers(int $subtotalCents, int $expectedShippingCents): void
    {
        $calculator = new ShippingRateCalculator();

        self::assertSame($expectedShippingCents, $calculator->amountForSubtotal($subtotalCents));
    }

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function shippingTiers(): iterable
    {
        yield 'zero subtotal' => [0, 800];
        yield 'below ten euros' => [999, 800];
        yield 'ten euros' => [1000, 600];
        yield 'below twenty euros' => [1999, 600];
        yield 'twenty euros' => [2000, 475];
        yield 'below thirty euros' => [2999, 475];
        yield 'thirty euros' => [3000, 350];
        yield 'below forty euros' => [3999, 350];
        yield 'forty euros' => [4000, 250];
        yield 'below free shipping' => [4999, 250];
        yield 'free shipping' => [5000, 0];
        yield 'above free shipping' => [12500, 0];
    }

    public function testQuoteExposesProgressAndCheckpoints(): void
    {
        $quote = (new ShippingRateCalculator())->quote(2450);

        self::assertSame(475, $quote['amountCents']);
        self::assertSame(49, $quote['progress']);
        self::assertSame(550, $quote['remainingToNextCents']);
        self::assertSame(350, $quote['nextShippingAmountCents']);
        self::assertFalse($quote['free']);
        self::assertCount(6, $quote['checkpoints']);
        self::assertTrue($quote['checkpoints'][2]['current']);
        self::assertFalse($quote['checkpoints'][3]['reached']);
        self::assertSame(100, $quote['checkpoints'][5]['position']);
    }
}
