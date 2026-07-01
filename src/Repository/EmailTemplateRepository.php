<?php

namespace App\Repository;

use App\Entity\EmailTemplate;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EmailTemplate>
 */
final class EmailTemplateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EmailTemplate::class);
    }

    /**
     * @return list<EmailTemplate>
     */
    public function findLatestForAdmin(int $limit = 20): array
    {
        return $this->createQueryBuilder('template')
            ->leftJoin('template.createdBy', 'createdBy')
            ->addSelect('createdBy')
            ->orderBy('template.createdAt', 'DESC')
            ->addOrderBy('template.id', 'DESC')
            ->setMaxResults(max(1, min(50, $limit)))
            ->getQuery()
            ->getResult();
    }
}
