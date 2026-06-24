<?php

namespace App\Repository;

use App\Entity\PromoCode;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PromoCode>
 */
final class PromoCodeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PromoCode::class);
    }

    public function findOneByCode(string $code): ?PromoCode
    {
        return $this->createQueryBuilder('promo')
            ->leftJoin('promo.assignedUser', 'assignedUser')
            ->addSelect('assignedUser')
            ->andWhere('UPPER(promo.code) = :code')
            ->setParameter('code', mb_strtoupper(trim($code)))
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return list<PromoCode>
     */
    public function findForAdmin(string $search = ''): array
    {
        $queryBuilder = $this->createQueryBuilder('promo')
            ->leftJoin('promo.assignedUser', 'assignedUser')
            ->addSelect('assignedUser')
            ->orderBy('promo.createdAt', 'DESC')
            ->addOrderBy('promo.id', 'DESC');

        if ('' !== $search) {
            $queryBuilder
                ->andWhere('LOWER(promo.code) LIKE :search OR LOWER(assignedUser.email) LIKE :search')
                ->setParameter('search', '%' . mb_strtolower($search) . '%');
        }

        return $queryBuilder->getQuery()->getResult();
    }
}
