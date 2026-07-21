<?php

namespace App\Repository;

use App\Entity\PopupSettings;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PopupSettings>
 */
final class PopupSettingsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PopupSettings::class);
    }

    public function findCurrent(): ?PopupSettings
    {
        return $this->createQueryBuilder('settings')
            ->leftJoin('settings.promoCode', 'promoCode')
            ->addSelect('promoCode')
            ->leftJoin('promoCode.assignedUser', 'assignedUser')
            ->addSelect('assignedUser')
            ->orderBy('settings.id', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
