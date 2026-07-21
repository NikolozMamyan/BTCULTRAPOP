<?php

namespace App\Twig;

use App\Service\StorefrontPopupProvider;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class StorefrontPopupExtension extends AbstractExtension
{
    public function __construct(private readonly StorefrontPopupProvider $popupProvider)
    {
    }

    /**
     * @return list<TwigFunction>
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('storefront_popup', $this->popupProvider->popup(...)),
        ];
    }
}
