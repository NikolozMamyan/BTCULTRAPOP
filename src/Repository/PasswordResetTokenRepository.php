<?php

namespace App\Repository;

use App\Entity\PasswordResetToken;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PasswordResetToken>
 */
final class PasswordResetTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PasswordResetToken::class);
    }

    public function findUsableBySelector(string $selector, \DateTimeImmutable $now): ?PasswordResetToken
    {
        return $this->createQueryBuilder('token')
            ->addSelect('user')
            ->innerJoin('token.user', 'user')
            ->andWhere('token.selector = :selector')
            ->andWhere('token.usedAt IS NULL')
            ->andWhere('token.expiresAt > :now')
            ->setParameter('selector', $selector)
            ->setParameter('now', $now)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
