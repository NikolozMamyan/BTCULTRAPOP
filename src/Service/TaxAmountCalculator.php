<?php

namespace App\Service;

final class TaxAmountCalculator
{
    public const SHIPPING_TAX_RATE = '20.00';

    public function taxIncluded(int $taxExcludedCents, string $taxRate): int
    {
        $taxExcludedCents = max(0, $taxExcludedCents);
        $rateBasisPoints = $this->rateBasisPoints($taxRate);

        return intdiv(($taxExcludedCents * (10_000 + $rateBasisPoints)) + 5_000, 10_000);
    }

    public function taxExcluded(int $taxIncludedCents, string $taxRate): int
    {
        $taxIncludedCents = max(0, $taxIncludedCents);
        $multiplier = 10_000 + $this->rateBasisPoints($taxRate);

        return intdiv(($taxIncludedCents * 10_000) + intdiv($multiplier, 2), $multiplier);
    }

    /**
     * @param list<array{taxExcludedCents: int, taxRate: string}> $taxableAmounts
     */
    public function taxIncludedDiscount(int $discountTaxExcludedCents, array $taxableAmounts): int
    {
        $discountTaxExcludedCents = max(0, $discountTaxExcludedCents);
        $taxableAmounts = array_values(array_filter(
            $taxableAmounts,
            static fn (array $amount): bool => $amount['taxExcludedCents'] > 0,
        ));
        $totalTaxExcludedCents = array_sum(array_column($taxableAmounts, 'taxExcludedCents'));

        if (0 === $discountTaxExcludedCents || 0 === $totalTaxExcludedCents) {
            return 0;
        }

        $remainingDiscountCents = min($discountTaxExcludedCents, $totalTaxExcludedCents);
        $remainingTaxExcludedCents = $totalTaxExcludedCents;
        $taxIncludedDiscountCents = 0;

        foreach ($taxableAmounts as $index => $amount) {
            $taxExcludedCents = max(0, $amount['taxExcludedCents']);

            if (0 === $taxExcludedCents) {
                continue;
            }

            $isLastAmount = $index === array_key_last($taxableAmounts);
            $allocatedDiscountCents = $isLastAmount
                ? $remainingDiscountCents
                : intdiv($remainingDiscountCents * $taxExcludedCents, $remainingTaxExcludedCents);

            $taxIncludedDiscountCents += $this->taxIncluded($allocatedDiscountCents, $amount['taxRate']);
            $remainingDiscountCents -= $allocatedDiscountCents;
            $remainingTaxExcludedCents -= $taxExcludedCents;
        }

        return $taxIncludedDiscountCents;
    }

    private function rateBasisPoints(string $taxRate): int
    {
        $normalizedRate = str_replace(',', '.', trim($taxRate));

        if (!is_numeric($normalizedRate)) {
            return 0;
        }

        return max(0, min(10_000, (int) round((float) $normalizedRate * 100)));
    }
}
