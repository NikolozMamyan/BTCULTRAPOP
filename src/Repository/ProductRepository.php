<?php

namespace App\Repository;

use App\Entity\Category;
use App\Entity\Product;
use App\Entity\User;
use App\Entity\UserFavorite;
use App\Enum\ProductStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
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
    public function findForPriceAdmin(): array
    {
        return $this->createQueryBuilder('product')
            ->addSelect('category', 'categoryParent', 'categoryGrandparent', 'license')
            ->innerJoin('product.category', 'category')
            ->leftJoin('category.parent', 'categoryParent')
            ->leftJoin('categoryParent.parent', 'categoryGrandparent')
            ->innerJoin('product.license', 'license')
            ->orderBy('categoryGrandparent.position', 'ASC')
            ->addOrderBy('categoryParent.position', 'ASC')
            ->addOrderBy('category.position', 'ASC')
            ->addOrderBy('product.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<Product>
     */
    public function findByCategoryForPriceAdmin(Category $category): array
    {
        return $this->createQueryBuilder('product')
            ->innerJoin('product.category', 'productCategory')
            ->leftJoin('productCategory.parent', 'categoryParent')
            ->leftJoin('categoryParent.parent', 'categoryGrandparent')
            ->andWhere(
                'productCategory = :category
                OR categoryParent = :category
                OR categoryGrandparent = :category',
            )
            ->setParameter('category', $category)
            ->orderBy('product.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<Product>
     */
    public function findForStockAdmin(): array
    {
        return $this->createQueryBuilder('product')
            ->orderBy('product.reference', 'ASC')
            ->addOrderBy('product.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<Product>
     */
    public function findForStorefront(): array
    {
        return $this->createStorefrontQueryBuilder('product')
            ->orderBy('product.quantity', 'DESC')
            ->addOrderBy('product.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOneForStorefront(int $id): ?Product
    {
        return $this->createStorefrontQueryBuilder('product')
            ->andWhere('product.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @param list<int> $ids
     *
     * @return list<Product>
     */
    public function findForStorefrontByIds(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        return $this->createStorefrontQueryBuilder('product')
            ->andWhere('product.id IN (:ids)')
            ->andWhere('product.quantity > 0')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();
    }

    /**
     * @param list<int> $excludedIds
     *
     * @return list<Product>
     */
    public function findHomeFallbackForStorefront(array $excludedIds = [], int $limit = 4): array
    {
        $queryBuilder = $this->createStorefrontQueryBuilder('product', false)
            ->addSelect(
                'CASE
                    WHEN product.status = :bestseller THEN 0
                    WHEN product.status = :new THEN 1
                    WHEN product.status = :promo THEN 2
                    ELSE 3
                END AS HIDDEN homePriority',
            )
            ->andWhere('product.quantity > 0')
            ->setParameter('bestseller', ProductStatus::BESTSELLER->value)
            ->setParameter('new', ProductStatus::NEW->value)
            ->setParameter('promo', ProductStatus::PROMO->value)
            ->orderBy('homePriority', 'ASC')
            ->addOrderBy('product.updatedAt', 'DESC')
            ->addOrderBy('product.quantity', 'DESC')
            ->addOrderBy('product.name', 'ASC')
            ->setMaxResults(max(1, min(8, $limit)));

        if ([] !== $excludedIds) {
            $queryBuilder
                ->andWhere('product.id NOT IN (:excludedIds)')
                ->setParameter('excludedIds', $excludedIds);
        }

        return $queryBuilder
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<Product>
     */
    public function searchForStorefront(string $query, int $limit = 8): array
    {
        $query = mb_strtolower(trim($query));

        if (mb_strlen($query) < 2) {
            return [];
        }

        return $this->createStorefrontQueryBuilder('product')
            ->addSelect('CASE WHEN LOWER(product.name) LIKE :startsWith THEN 0 ELSE 1 END AS HIDDEN relevance')
            ->andWhere(
                'LOWER(product.name) LIKE :query
                OR LOWER(product.reference) LIKE :query
                OR LOWER(COALESCE(product.ean, \'\')) LIKE :query
                OR LOWER(category.name) LIKE :query
                OR LOWER(license.name) LIKE :query',
            )
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('relevance', 'ASC')
            ->addOrderBy('product.quantity', 'DESC')
            ->addOrderBy('product.name', 'ASC')
            ->setParameter('startsWith', $query . '%')
            ->setMaxResults(max(1, min(12, $limit)))
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<Product>
     */
    public function findRelatedForStorefront(Product $product, int $limit = 3): array
    {
        return $this->createStorefrontQueryBuilder('related')
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
     * @return list<Product>
     */
    public function findFavoritesForStorefront(User $user): array
    {
        return $this->createStorefrontQueryBuilder('product')
            ->innerJoin(UserFavorite::class, 'favorite', 'WITH', 'favorite.product = product')
            ->andWhere('favorite.user = :user')
            ->setParameter('user', $user)
            ->orderBy('favorite.createdAt', 'DESC')
            ->addOrderBy('product.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    private function createStorefrontQueryBuilder(string $productAlias, bool $withImages = true): QueryBuilder
    {
        $queryBuilder = $this->createQueryBuilder($productAlias)
            ->addSelect('category', 'categoryParent', 'categoryGrandparent', 'license')
            ->innerJoin($productAlias . '.category', 'category')
            ->leftJoin('category.parent', 'categoryParent')
            ->leftJoin('categoryParent.parent', 'categoryGrandparent')
            ->innerJoin($productAlias . '.license', 'license')
            ->andWhere($productAlias . '.active = true')
            ->andWhere('category.active = true')
            ->andWhere('(categoryParent.id IS NULL OR categoryParent.active = true)')
            ->andWhere('(categoryGrandparent.id IS NULL OR categoryGrandparent.active = true)')
            ->andWhere('license.active = true')
        ;

        if ($withImages) {
            $queryBuilder
                ->addSelect('images')
                ->leftJoin($productAlias . '.images', 'images');
        }

        return $queryBuilder;
    }
}
