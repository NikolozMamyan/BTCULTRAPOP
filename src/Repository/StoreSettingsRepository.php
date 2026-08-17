<?php

namespace App\Repository;

use App\Entity\StoreSettings;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<StoreSettings>
 */
final class StoreSettingsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StoreSettings::class);
    }

    public function findCurrent(): ?StoreSettings
    {
        return $this->createQueryBuilder('settings')
            ->orderBy('settings.id', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
