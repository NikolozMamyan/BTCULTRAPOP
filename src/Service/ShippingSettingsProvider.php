<?php

namespace App\Service;

use App\Entity\ShippingRateTier;
use App\Entity\ShippingSettings;
use App\Repository\ShippingSettingsRepository;

final readonly class ShippingSettingsProvider implements ShippingConfigurationProviderInterface
{
    public function __construct(private ShippingSettingsRepository $settings)
    {
    }

    public function configuration(): array
    {
        $settings = $this->settings->findCurrent();

        if (!$settings instanceof ShippingSettings) {
            return $this->defaults();
        }

        $tiers = array_map(
            static fn (ShippingRateTier $tier): array => [
                'thresholdCents' => $tier->getThresholdCents(),
                'shippingAmountCents' => $tier->getShippingAmountCents(),
            ],
            $settings->getSortedTiers(),
        );

        if ([] === $tiers) {
            return $this->defaults();
        }

        return [
            'minimumOrderCents' => $settings->getMinimumOrderCents(),
            'tiers' => $tiers,
        ];
    }

    /**
     * @return array{
     *     minimumOrderCents: int,
     *     tiers: list<array{thresholdCents: int, shippingAmountCents: int}>
     * }
     */
    private function defaults(): array
    {
        return [
            'minimumOrderCents' => ShippingSettings::DEFAULT_MINIMUM_ORDER_CENTS,
            'tiers' => ShippingSettings::DEFAULT_TIERS,
        ];
    }
}
