<?php

namespace App\Repository;

use App\Entity\License;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<License>
 */
final class LicenseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, License::class);
    }

    /**
     * @return list<License>
     */
    public function findForAdmin(?string $search = null): array
    {
        $queryBuilder = $this->createQueryBuilder('license')
            ->addSelect('products')
            ->leftJoin('license.products', 'products')
            ->orderBy('license.updatedAt', 'DESC')
            ->addOrderBy('license.name', 'ASC');

        $search = null === $search ? null : trim($search);

        if (null !== $search && '' !== $search) {
            $queryBuilder
                ->andWhere('LOWER(license.name) LIKE :search OR LOWER(COALESCE(license.description, \'\')) LIKE :search')
                ->setParameter('search', '%' . mb_strtolower($search) . '%');
        }

        return $queryBuilder
            ->getQuery()
            ->getResult();
    }
}
