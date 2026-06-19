<?php

namespace App\Service;

final class ProductPriceCalculator
{
    public function normalizeTaxExcluded(string $amount): string
    {
        return $this->normalizeDecimal($amount, 6, 14);
    }

    public function normalizeTaxRate(string $taxRate): string
    {
        $rawTaxRate = str_replace([' ', ','], ['', '.'], trim($taxRate));

        if (!is_numeric($rawTaxRate) || (float) $rawTaxRate < 0 || (float) $rawTaxRate > 100) {
            throw new \InvalidArgumentException('admin.price.error.invalid_tax_rate');
        }

        $normalized = $this->normalizeDecimal($taxRate, 2, 5);

        return $normalized;
    }

    public function taxIncluded(string $taxExcluded, string $taxRate): string
    {
        $taxExcluded = $this->normalizeTaxExcluded($taxExcluded);
        $taxRate = $this->normalizeTaxRate($taxRate);
        $taxExcludedMicros = $this->decimalToScaledInteger($taxExcluded, 6);
        $taxRateBasisPoints = $this->decimalToScaledInteger($taxRate, 2);
        $taxIncludedMicros = intdiv(
            ($taxExcludedMicros * (10_000 + $taxRateBasisPoints)) + 5_000,
            10_000,
        );

        return $this->scaledIntegerToDecimal($taxIncludedMicros, 6);
    }

    private function normalizeDecimal(string $value, int $scale, int $precision): string
    {
        $value = str_replace([' ', ','], ['', '.'], trim($value));

        if (!preg_match('/^\d+(?:\.\d+)?$/', $value)) {
            throw new \InvalidArgumentException('admin.price.error.invalid_amount');
        }

        [$units, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $maximumIntegerDigits = $precision - $scale;

        if (\strlen(ltrim($units, '0') ?: '0') > $maximumIntegerDigits) {
            throw new \InvalidArgumentException('admin.price.error.invalid_amount');
        }

        $fraction = str_pad(substr($fraction, 0, $scale + 1), $scale + 1, '0');
        $scaled = ((int) $units * (10 ** $scale)) + (int) substr($fraction, 0, $scale);

        if ((int) $fraction[$scale] >= 5) {
            ++$scaled;
        }

        return $this->scaledIntegerToDecimal($scaled, $scale);
    }

    private function decimalToScaledInteger(string $value, int $scale): int
    {
        [$units, $fraction] = array_pad(explode('.', $value, 2), 2, '');

        return ((int) $units * (10 ** $scale))
            + (int) str_pad(substr($fraction, 0, $scale), $scale, '0');
    }

    private function scaledIntegerToDecimal(int $value, int $scale): string
    {
        $divisor = 10 ** $scale;

        return sprintf('%d.%0' . $scale . 'd', intdiv($value, $divisor), $value % $divisor);
    }
}
