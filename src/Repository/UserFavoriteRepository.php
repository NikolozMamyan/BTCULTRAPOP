<?php

namespace App\Repository;

use App\Entity\Product;
use App\Entity\User;
use App\Entity\UserFavorite;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserFavorite>
 */
final class UserFavoriteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserFavorite::class);
    }

    public function findOneForUserAndProduct(User $user, Product $product): ?UserFavorite
    {
        return $this->findOneBy([
            'user' => $user,
            'product' => $product,
        ]);
    }

    /**
     * @return list<int>
     */
    public function findProductIdsForUser(User $user): array
    {
        $rows = $this->createQueryBuilder('favorite')
            ->select('product.id AS id')
            ->innerJoin('favorite.product', 'product')
            ->andWhere('favorite.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getScalarResult();

        return array_map(static fn (array $row): int => (int) $row['id'], $rows);
    }

    public function countForUser(User $user): int
    {
        return (int) $this->createQueryBuilder('favorite')
            ->select('COUNT(favorite.id)')
            ->andWhere('favorite.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
