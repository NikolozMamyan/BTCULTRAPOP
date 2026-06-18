<?php

namespace App\Repository;

use App\Entity\CartItem;
use App\Enum\CartStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CartItem>
 */
final class CartItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CartItem::class);
    }

    /**
     * @return list<int>
     */
    public function findTrendingProductIds(\DateTimeImmutable $activeSince, int $limit = 4): array
    {
        $rows = $this->createQueryBuilder('item')
            ->select('IDENTITY(item.product) AS productId')
            ->addSelect('COUNT(DISTINCT cart.id) AS HIDDEN activeCartCount')
            ->addSelect('SUM(item.quantity) AS HIDDEN totalQuantity')
            ->addSelect('MAX(item.updatedAt) AS HIDDEN lastActivity')
            ->innerJoin('item.cart', 'cart')
            ->innerJoin('item.product', 'product')
            ->innerJoin('product.category', 'category')
            ->innerJoin('product.license', 'license')
            ->andWhere('cart.status = :activeStatus')
            ->andWhere('(cart.expiresAt IS NULL OR cart.expiresAt > :now)')
            ->andWhere('item.updatedAt >= :activeSince')
            ->andWhere('product.active = true')
            ->andWhere('product.quantity > 0')
            ->andWhere('category.active = true')
            ->andWhere('license.active = true')
            ->setParameter('activeStatus', CartStatus::ACTIVE->value)
            ->setParameter('now', new \DateTimeImmutable())
            ->setParameter('activeSince', $activeSince)
            ->groupBy('product.id')
            ->orderBy('activeCartCount', 'DESC')
            ->addOrderBy('totalQuantity', 'DESC')
            ->addOrderBy('lastActivity', 'DESC')
            ->setMaxResults(max(1, min(8, $limit)))
            ->getQuery()
            ->getScalarResult();

        return array_map(
            static fn (array $row): int => (int) $row['productId'],
            $rows,
        );
    }
}
