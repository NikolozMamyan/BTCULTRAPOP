<?php

namespace App\Service;

use App\Entity\ShippingSettings;

final class ShippingRateCalculator
{
    /**
     * @deprecated Use the configurable free tier returned by quote().
     */
    public const FREE_SHIPPING_THRESHOLD_CENTS = 5000;

    public function __construct(private readonly ?ShippingConfigurationProviderInterface $configurationProvider = null)
    {
    }

    public function amountForSubtotal(int $subtotalCents): int
    {
        $subtotalCents = max(0, $subtotalCents);
        $configuration = $this->configuration();

        return $this->amountForTiers($subtotalCents, $configuration['tiers']);
    }

    public function minimumOrderCents(): int
    {
        return $this->configuration()['minimumOrderCents'];
    }

    public function isMinimumReached(int $subtotalCents): bool
    {
        return max(0, $subtotalCents) >= $this->minimumOrderCents();
    }

    public function remainingToMinimumCents(int $subtotalCents): int
    {
        return max(0, $this->minimumOrderCents() - max(0, $subtotalCents));
    }

    /**
     * @param list<array{thresholdCents: int, shippingAmountCents: int}> $tiers
     */
    private function amountForTiers(int $subtotalCents, array $tiers): int
    {
        $amount = $tiers[0]['shippingAmountCents'];

        foreach ($tiers as $tier) {
            if ($subtotalCents < $tier['thresholdCents']) {
                break;
            }

            $amount = $tier['shippingAmountCents'];
        }

        return $amount;
    }

    /**
     * @return array{
     *     amountCents: int,
     *     minimumOrderCents: int,
     *     minimumReached: bool,
     *     remainingToMinimumCents: int,
     *     progress: int,
     *     freeShippingThresholdCents: int,
     *     nextShippingAmountCents: ?int,
     *     remainingToNextCents: int,
     *     free: bool,
     *     checkpoints: list<array{
     *         thresholdCents: int,
     *         shippingAmountCents: int,
     *         position: int,
     *         reached: bool,
     *         current: bool
     *     }>
     * }
     */
    public function quote(int $subtotalCents): array
    {
        $subtotalCents = max(0, $subtotalCents);
        $configuration = $this->configuration();
        $tiers = $configuration['tiers'];
        $minimumOrderCents = $configuration['minimumOrderCents'];
        $amountCents = $this->amountForTiers($subtotalCents, $tiers);
        $currentIndex = 0;

        foreach ($tiers as $index => $tier) {
            if ($subtotalCents < $tier['thresholdCents']) {
                break;
            }

            $currentIndex = $index;
        }

        $currentTier = $tiers[$currentIndex];
        $nextTier = $tiers[$currentIndex + 1] ?? null;
        $freeShippingThresholdCents = $this->freeShippingThresholdCents($tiers);

        return [
            'amountCents' => $amountCents,
            'minimumOrderCents' => $minimumOrderCents,
            'minimumReached' => $subtotalCents >= $minimumOrderCents,
            'remainingToMinimumCents' => max(0, $minimumOrderCents - $subtotalCents),
            'progress' => min(100, (int) round(($subtotalCents / max(1, $freeShippingThresholdCents)) * 100)),
            'freeShippingThresholdCents' => $freeShippingThresholdCents,
            'nextShippingAmountCents' => $nextTier['shippingAmountCents'] ?? null,
            'remainingToNextCents' => null === $nextTier
                ? 0
                : max(0, $nextTier['thresholdCents'] - $subtotalCents),
            'free' => 0 === $amountCents,
            'checkpoints' => array_map(
                static fn (array $tier): array => [
                    ...$tier,
                    'position' => min(100, (int) round(($tier['thresholdCents'] / max(1, $freeShippingThresholdCents)) * 100)),
                    'reached' => $subtotalCents >= $tier['thresholdCents'],
                    'current' => $currentTier['thresholdCents'] === $tier['thresholdCents'],
                ],
                $tiers,
            ),
        ];
    }

    /**
     * @return array{
     *     minimumOrderCents: int,
     *     tiers: list<array{thresholdCents: int, shippingAmountCents: int}>
     * }
     */
    private function configuration(): array
    {
        return $this->configurationProvider?->configuration() ?? [
            'minimumOrderCents' => ShippingSettings::DEFAULT_MINIMUM_ORDER_CENTS,
            'tiers' => ShippingSettings::DEFAULT_TIERS,
        ];
    }

    /**
     * @param list<array{thresholdCents: int, shippingAmountCents: int}> $tiers
     */
    private function freeShippingThresholdCents(array $tiers): int
    {
        foreach (array_reverse($tiers) as $tier) {
            if (0 === $tier['shippingAmountCents']) {
                return $tier['thresholdCents'];
            }
        }

        return $tiers[array_key_last($tiers)]['thresholdCents'];
    }
}
