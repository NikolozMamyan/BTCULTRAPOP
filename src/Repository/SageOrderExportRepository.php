<?php

namespace App\Repository;

use App\Entity\SageOrderExport;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SageOrderExport>
 */
final class SageOrderExportRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SageOrderExport::class);
    }

    /**
     * @param list<int> $orderIds
     *
     * @return array<int, SageOrderExport>
     */
    public function findIndexedByOrderIds(array $orderIds): array
    {
        $orderIds = array_values(array_unique(array_filter($orderIds)));

        if ([] === $orderIds) {
            return [];
        }

        $exports = $this->createQueryBuilder('export')
            ->innerJoin('export.customerOrder', 'customerOrder')
            ->addSelect('customerOrder')
            ->andWhere('customerOrder.id IN (:orderIds)')
            ->setParameter('orderIds', $orderIds)
            ->getQuery()
            ->getResult();

        $indexed = [];

        foreach ($exports as $export) {
            \assert($export instanceof SageOrderExport);
            $orderId = $export->getOrder()?->getId();

            if (null !== $orderId) {
                $indexed[$orderId] = $export;
            }
        }

        return $indexed;
    }
}
