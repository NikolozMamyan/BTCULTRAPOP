<?php

namespace App\Repository;

use App\Entity\Product;
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
    public function findForStorefront(): array
    {
        return $this->createQueryBuilder('product')
            ->addSelect('category', 'license', 'images')
            ->innerJoin('product.category', 'category')
            ->innerJoin('product.license', 'license')
            ->leftJoin('product.images', 'images')
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
     * @return list<string>
     */
    public function findStorefrontCategoryNames(): array
    {
        $rows = $this->createQueryBuilder('product')
            ->select('category.name AS name')
            ->innerJoin('product.category', 'category')
            ->groupBy('category.id')
            ->addGroupBy('category.name')
            ->orderBy('category.name', 'ASC')
            ->getQuery()
            ->getScalarResult();

        return array_map(static fn (array $row): string => (string) $row['name'], $rows);
    }
}
