<?php

namespace App\Repository;

use App\Entity\Product;
use App\Entity\User;
use App\Entity\UserFavorite;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Product>
 */
final class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    /**
     * @return list<Product>
     */
    public function findForAdmin(?string $search = null): array
    {
        $queryBuilder = $this->createQueryBuilder('product')
            ->addSelect('category', 'license', 'images')
            ->innerJoin('product.category', 'category')
            ->innerJoin('product.license', 'license')
            ->leftJoin('product.images', 'images')
            ->orderBy('product.updatedAt', 'DESC')
            ->addOrderBy('product.name', 'ASC');

        $search = null === $search ? null : trim($search);

        if (null !== $search && '' !== $search) {
            $queryBuilder
                ->andWhere(
                    'LOWER(product.name) LIKE :search
                    OR LOWER(product.reference) LIKE :search
                    OR LOWER(COALESCE(product.ean, \'\')) LIKE :search
                    OR LOWER(category.name) LIKE :search
                    OR LOWER(license.name) LIKE :search',
                )
                ->setParameter('search', '%' . mb_strtolower($search) . '%');
        }

        return $queryBuilder
            ->getQuery()
            ->getResult();
    }

    public function findOneByEanForAdmin(string $ean): ?Product
    {
        return $this->createQueryBuilder('product')
            ->addSelect('category', 'license', 'images')
            ->innerJoin('product.category', 'category')
            ->innerJoin('product.license', 'license')
            ->leftJoin('product.images', 'images')
            ->andWhere('product.ean = :ean')
            ->setParameter('ean', trim($ean))
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return list<Product>
     */
    public function findForStorefront(): array
    {
        return $this->createQueryBuilder('product')
            ->addSelect('category', 'license', 'images')
            ->innerJoin('product.category', 'category')
            ->innerJoin('product.license', 'license')
            ->leftJoin('product.images', 'images')
            ->andWhere('product.active = true')
            ->orderBy('product.quantity', 'DESC')
            ->addOrderBy('product.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOneForStorefront(int $id): ?Product
    {
        return $this->createQueryBuilder('product')
            ->addSelect('category', 'license', 'images')
            ->innerJoin('product.category', 'category')
            ->innerJoin('product.license', 'license')
            ->leftJoin('product.images', 'images')
            ->andWhere('product.id = :id')
            ->andWhere('product.active = true')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return list<Product>
     */
    public function findRelatedForStorefront(Product $product, int $limit = 3): array
    {
        return $this->createQueryBuilder('related')
            ->addSelect('category', 'license', 'images')
            ->innerJoin('related.category', 'category')
            ->innerJoin('related.license', 'license')
            ->leftJoin('related.images', 'images')
            ->andWhere('related.id != :id')
            ->andWhere('related.active = true')
            ->andWhere('related.category = :category OR related.license = :license')
            ->setParameter('id', $product->getId())
            ->setParameter('category', $product->getCategory())
            ->setParameter('license', $product->getLicense())
            ->orderBy('related.quantity', 'DESC')
            ->addOrderBy('related.name', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<Product>
     */
    public function findFavoritesForStorefront(User $user): array
    {
        return $this->createQueryBuilder('product')
            ->addSelect('category', 'license', 'images')
            ->innerJoin('product.category', 'category')
            ->innerJoin('product.license', 'license')
            ->innerJoin(UserFavorite::class, 'favorite', 'WITH', 'favorite.product = product')
            ->leftJoin('product.images', 'images')
            ->andWhere('favorite.user = :user')
            ->andWhere('product.active = true')
            ->setParameter('user', $user)
            ->orderBy('favorite.createdAt', 'DESC')
            ->addOrderBy('product.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<string>
     */
    public function findStorefrontCategoryNames(): array
    {
        $rows = $this->createQueryBuilder('product')
            ->select('category.name AS name')
            ->innerJoin('product.category', 'category')
            ->andWhere('product.active = true')
            ->groupBy('category.id')
            ->addGroupBy('category.name')
            ->orderBy('category.name', 'ASC')
            ->getQuery()
            ->getScalarResult();

        return array_map(static fn (array $row): string => (string) $row['name'], $rows);
    }
}
