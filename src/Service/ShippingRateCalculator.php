<?php

namespace App\Service;

final class ShippingRateCalculator
{
    public const FREE_SHIPPING_THRESHOLD_CENTS = 5000;

    /**
     * @var list<array{thresholdCents: int, shippingAmountCents: int}>
     */
    private const TIERS = [
        ['thresholdCents' => 0, 'shippingAmountCents' => 800],
        ['thresholdCents' => 1000, 'shippingAmountCents' => 600],
        ['thresholdCents' => 2000, 'shippingAmountCents' => 475],
        ['thresholdCents' => 3000, 'shippingAmountCents' => 350],
        ['thresholdCents' => 4000, 'shippingAmountCents' => 250],
        ['thresholdCents' => self::FREE_SHIPPING_THRESHOLD_CENTS, 'shippingAmountCents' => 0],
    ];

    public function amountForSubtotal(int $subtotalCents): int
    {
        $subtotalCents = max(0, $subtotalCents);
        $amount = self::TIERS[0]['shippingAmountCents'];

        foreach (self::TIERS as $tier) {
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
     *     progress: int,
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
        $amountCents = $this->amountForSubtotal($subtotalCents);
        $currentIndex = 0;

        foreach (self::TIERS as $index => $tier) {
            if ($subtotalCents < $tier['thresholdCents']) {
                break;
            }

            $currentIndex = $index;
        }

        $currentTier = self::TIERS[$currentIndex];
        $nextTier = self::TIERS[$currentIndex + 1] ?? null;

        return [
            'amountCents' => $amountCents,
            'progress' => min(100, (int) round(($subtotalCents / self::FREE_SHIPPING_THRESHOLD_CENTS) * 100)),
            'nextShippingAmountCents' => $nextTier['shippingAmountCents'] ?? null,
            'remainingToNextCents' => null === $nextTier
                ? 0
                : max(0, $nextTier['thresholdCents'] - $subtotalCents),
            'free' => 0 === $amountCents,
            'checkpoints' => array_map(
                static fn (array $tier): array => [
                    ...$tier,
                    'position' => (int) round(($tier['thresholdCents'] / self::FREE_SHIPPING_THRESHOLD_CENTS) * 100),
                    'reached' => $subtotalCents >= $tier['thresholdCents'],
                    'current' => $currentTier['thresholdCents'] === $tier['thresholdCents'],
                ],
                self::TIERS,
            ),
        ];
    }
}
