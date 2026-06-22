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
            ->addSelect('parent', 'grandparent', 'children', 'products')
            ->leftJoin('category.parent', 'parent')
            ->leftJoin('parent.parent', 'grandparent')
            ->leftJoin('category.children', 'children')
            ->leftJoin('category.products', 'products')
            ->addSelect(
                'CASE
                    WHEN parent.id IS NULL THEN 0
                    WHEN grandparent.id IS NULL THEN 1
                    ELSE 2
                END AS HIDDEN hierarchyDepth',
            )
            ->orderBy('hierarchyDepth', 'ASC')
            ->addOrderBy('parent.position', 'ASC')
            ->addOrderBy('category.position', 'ASC')
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

    /**
     * @return list<Category>
     */
    public function findAssignable(): array
    {
        return $this->createQueryBuilder('category')
            ->addSelect('parent', 'grandparent')
            ->leftJoin('category.parent', 'parent')
            ->leftJoin('parent.parent', 'grandparent')
            ->leftJoin('category.children', 'children')
            ->andWhere('children.id IS NULL')
            ->addSelect(
                'CASE
                    WHEN parent.id IS NULL THEN 0
                    WHEN grandparent.id IS NULL THEN 1
                    ELSE 2
                END AS HIDDEN hierarchyDepth',
            )
            ->orderBy('hierarchyDepth', 'ASC')
            ->addOrderBy('parent.position', 'ASC')
            ->addOrderBy('category.position', 'ASC')
            ->addOrderBy('category.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<Category>
     */
    public function findParentChoices(Category $category): array
    {
        return array_values(array_filter(
            $this->findForAdmin(),
            static fn (Category $candidate): bool => $candidate !== $category
                && !$candidate->isDescendantOf($category)
                && $candidate->getDepth() < Category::MAX_DEPTH
                && 0 === $candidate->getProducts()->count(),
        ));
    }
}
