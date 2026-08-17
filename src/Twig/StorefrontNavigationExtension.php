<?php

namespace App\Twig;

use App\Entity\User;
use App\Repository\UserFavoriteRepository;
use App\Service\StorefrontSalesAvailability;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class StorefrontNavigationExtension extends AbstractExtension
{
    public function __construct(
        private readonly StorefrontSalesAvailability $salesAvailability,
        private readonly UserFavoriteRepository $favorites,
        private readonly Security $security,
    ) {
    }

    /**
     * @return list<TwigFunction>
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('storefront_has_sales', $this->salesAvailability->hasSales(...)),
            new TwigFunction('storefront_favorite_count', $this->favoriteCount(...)),
        ];
    }

    public function favoriteCount(): int
    {
        $user = $this->security->getUser();

        return $user instanceof User
            ? $this->favorites->countForUser($user)
            : 0;
    }
}
