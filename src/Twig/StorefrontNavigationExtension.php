<?php

namespace App\Twig;

use App\Service\StorefrontSalesAvailability;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class StorefrontNavigationExtension extends AbstractExtension
{
    public function __construct(private readonly StorefrontSalesAvailability $salesAvailability)
    {
    }

    /**
     * @return list<TwigFunction>
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('storefront_has_sales', $this->salesAvailability->hasSales(...)),
        ];
    }
}
