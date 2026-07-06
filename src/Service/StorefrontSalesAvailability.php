<?php

namespace App\Service;

use App\Repository\ProductRepository;

final class StorefrontSalesAvailability
{
    private ?bool $hasSales = null;

    public function __construct(private readonly ProductRepository $products)
    {
    }

    public function hasSales(): bool
    {
        return $this->hasSales ??= $this->products->hasSalesForStorefront();
    }
}
