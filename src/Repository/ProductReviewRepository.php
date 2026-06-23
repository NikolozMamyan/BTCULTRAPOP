<?php

namespace App\Repository;

use App\Entity\Product;
use App\Entity\ProductReview;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProductReview>
 */
final class ProductReviewRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductReview::class);
    }

    /**
     * @return list<ProductReview>
     */
    public function findPublishedForProduct(Product $product): array
    {
        return $this->createQueryBuilder('review')
            ->andWhere('review.product = :product')
            ->andWhere('review.published = true')
            ->setParameter('product', $product)
            ->orderBy('review.createdAt', 'DESC')
            ->addOrderBy('review.id', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
