<?php

namespace App\Service;

use App\Entity\License;
use Doctrine\ORM\EntityManagerInterface;

final class AdminLicenseManager
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function save(License $license): void
    {
        foreach ($license->getProducts() as $product) {
            $product->setActive($license->isActive());
        }

        $this->entityManager->persist($license);
        $this->entityManager->flush();
    }
}
