<?php

namespace App\Repository;

use App\Entity\Category;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Category>
 */
final class CategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Category::class);
    }

    /**
     * @return list<Category>
     */
    public function findForAdmin(?string $search = null): array
    {
        $queryBuilder = $this->createQueryBuilder('category')
            ->addSelect('products')
            ->leftJoin('category.products', 'products')
            ->orderBy('category.updatedAt', 'DESC')
            ->addOrderBy('category.name', 'ASC');

        $search = null === $search ? null : trim($search);

        if (null !== $search && '' !== $search) {
            $queryBuilder
                ->andWhere('LOWER(category.name) LIKE :search OR LOWER(COALESCE(category.description, \'\')) LIKE :search')
                ->setParameter('search', '%' . mb_strtolower($search) . '%');
        }

        return $queryBuilder
            ->getQuery()
            ->getResult();
    }
}
