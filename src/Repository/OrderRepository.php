<?php

namespace App\Repository;

use App\Entity\Order;
use App\Enum\OrderStatus;
use App\Enum\PaymentStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Order>
 */
final class OrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Order::class);
    }

    /**
     * @return list<Order>
     */
    public function findForAdmin(string $search = '', string $status = '', string $paymentStatus = ''): array
    {
        $queryBuilder = $this->createQueryBuilder('o')
            ->leftJoin('o.user', 'u')
            ->addSelect('u')
            ->orderBy('o.createdAt', 'DESC')
            ->addOrderBy('o.id', 'DESC')
            ->setMaxResults(200);

        $search = mb_strtolower(trim($search));

        if ('' !== $search) {
            $queryBuilder
                ->andWhere(
                    $queryBuilder->expr()->orX(
                        'LOWER(o.orderNumber) LIKE :search',
                        'LOWER(o.customerName) LIKE :search',
                        'LOWER(o.customerEmail) LIKE :search',
                        'LOWER(o.shippingCity) LIKE :search',
                        'LOWER(o.stripeCheckoutSessionId) LIKE :search',
                        'LOWER(o.stripePaymentIntentId) LIKE :search',
                    ),
                )
                ->setParameter('search', '%' . $search . '%');
        }

        $orderStatus = $this->orderStatus($status);

        if ($orderStatus instanceof OrderStatus) {
            $queryBuilder
                ->andWhere('o.status = :status')
                ->setParameter('status', $orderStatus);
        }

        $paymentStatus = $this->paymentStatus($paymentStatus);

        if ($paymentStatus instanceof PaymentStatus) {
            $queryBuilder
                ->andWhere('o.paymentStatus = :paymentStatus')
                ->setParameter('paymentStatus', $paymentStatus);
        }

        return $queryBuilder->getQuery()->getResult();
    }

    public function paidRevenueCents(): int
    {
        return (int) $this->createQueryBuilder('o')
            ->select('COALESCE(SUM(o.totalTaxIncludedCents), 0)')
            ->andWhere('o.paymentStatus = :paid')
            ->setParameter('paid', PaymentStatus::PAID)
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function orderStatus(string $status): ?OrderStatus
    {
        if ('' === trim($status)) {
            return null;
        }

        return OrderStatus::tryFrom($status);
    }

    private function paymentStatus(string $status): ?PaymentStatus
    {
        if ('' === trim($status)) {
            return null;
        }

        return PaymentStatus::tryFrom($status);
    }
}
