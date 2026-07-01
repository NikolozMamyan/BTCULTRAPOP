<?php

namespace App\Repository;

use App\Entity\Product;
use App\Entity\ProductModel3D;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProductModel3D>
 */
final class ProductModel3DRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductModel3D::class);
    }

    /**
     * @param list<Product> $products
     *
     * @return array<int, ProductModel3D>
     */
    public function findIndexedByProduct(array $products): array
    {
        $ids = array_values(array_filter(
            array_map(static fn (Product $product): ?int => $product->getId(), $products),
        ));

        if ([] === $ids) {
            return [];
        }

        $models = $this->createQueryBuilder('model')
            ->addSelect('product')
            ->innerJoin('model.product', 'product')
            ->andWhere('product.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();

        $indexed = [];

        foreach ($models as $model) {
            \assert($model instanceof ProductModel3D);
            $productId = $model->getProduct()?->getId();

            if (null !== $productId) {
                $indexed[$productId] = $model;
            }
        }

        return $indexed;
    }
}
