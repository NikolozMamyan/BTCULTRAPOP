<?php

namespace App\Repository;

use App\Entity\ShippingSettings;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ShippingSettings>
 */
final class ShippingSettingsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ShippingSettings::class);
    }

    public function findCurrent(): ?ShippingSettings
    {
        return $this->findOneBy([], ['id' => 'ASC']);
    }
}
