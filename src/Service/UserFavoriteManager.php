<?php

namespace App\Service;

use App\Entity\Product;
use App\Entity\User;
use App\Entity\UserFavorite;
use App\Repository\UserFavoriteRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class UserFavoriteManager
{
    public function __construct(
        private UserFavoriteRepository $favorites,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function toggle(User $user, Product $product): bool
    {
        $favorite = $this->favorites->findOneForUserAndProduct($user, $product);

        if ($favorite instanceof UserFavorite) {
            $this->entityManager->remove($favorite);
            $this->entityManager->flush();

            return false;
        }

        $favorite = (new UserFavorite())
            ->setUser($user)
            ->setProduct($product);

        $this->entityManager->persist($favorite);
        $this->entityManager->flush();

        return true;
    }
}
