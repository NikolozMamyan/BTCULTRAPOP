<?php

namespace App\Service;

use App\Entity\PopupSettings;
use App\Entity\PromoCode;
use App\Enum\PromoDiscountType;
use App\Repository\PopupSettingsRepository;
use Doctrine\DBAL\Exception as DbalException;

final class StorefrontPopupProvider
{
    /** @var array<string, mixed>|null|false */
    private array|false|null $popup = false;

    public function __construct(private readonly PopupSettingsRepository $settings)
    {
    }

    /**
     * @return array{
     *     title: string,
     *     message: string,
     *     code: string,
     *     value: string,
     *     percentage: bool,
     *     shipping: bool,
     *     version: string
     * }|null
     */
    public function popup(): ?array
    {
        if (false !== $this->popup) {
            return $this->popup;
        }

        try {
            $settings = $this->settings->findCurrent();
        } catch (DbalException) {
            // This optional marketing element must never make the storefront unavailable.
            $this->popup = null;

            return null;
        }

        $promoCode = $settings?->getPromoCode();

        if (!($settings instanceof PopupSettings)
            || !$settings->isActive()
            || !($promoCode instanceof PromoCode)
            || !$promoCode->isAvailableFor(null)
        ) {
            $this->popup = null;

            return null;
        }

        $this->popup = [
            'title' => $settings->getTitle(),
            'message' => $settings->getMessage(),
            'code' => $promoCode->getCode(),
            'value' => $promoCode->getValue(),
            'percentage' => PromoDiscountType::PERCENTAGE === $promoCode->getDiscountType(),
            'shipping' => $promoCode->appliesToShipping(),
            'version' => sha1(implode('|', [
                (string) $settings->getId(),
                $settings->getUpdatedAt()->format('U.u'),
                $promoCode->getCode(),
                $promoCode->getUpdatedAt()->format('U.u'),
            ])),
        ];

        return $this->popup;
    }
}
