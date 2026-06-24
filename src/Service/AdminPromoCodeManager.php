<?php

namespace App\Service;

use App\Entity\PromoCode;
use Doctrine\ORM\EntityManagerInterface;

final readonly class AdminPromoCodeManager
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function save(PromoCode $promoCode): void
    {
        $this->entityManager->persist($promoCode);
        $this->entityManager->flush();
    }
}
