<?php

namespace App\Service;

interface ShippingConfigurationProviderInterface
{
    /**
     * @return array{
     *     minimumOrderCents: int,
     *     tiers: list<array{thresholdCents: int, shippingAmountCents: int}>
     * }
     */
    public function configuration(): array;
}
