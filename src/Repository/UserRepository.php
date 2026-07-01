<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Security\User\UserLoaderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
final class UserRepository extends ServiceEntityRepository implements UserLoaderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function loadUserByIdentifier(string $identifier): ?User
    {
        return $this->createQueryBuilder('user')
            ->andWhere('LOWER(user.email) = :email')
            ->setParameter('email', mb_strtolower(trim($identifier)))
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return list<User>
     */
    public function findForAdmin(string $search = ''): array
    {
        $queryBuilder = $this->createQueryBuilder('user')
            ->orderBy('user.createdAt', 'DESC')
            ->addOrderBy('user.id', 'DESC')
            ->setMaxResults(200);

        $search = mb_strtolower(trim($search));

        if ('' !== $search) {
            $queryBuilder
                ->andWhere(
                    $queryBuilder->expr()->orX(
                        'LOWER(user.email) LIKE :search',
                        'LOWER(user.firstName) LIKE :search',
                        'LOWER(user.lastName) LIKE :search',
                        'LOWER(user.phone) LIKE :search',
                    ),
                )
                ->setParameter('search', '%' . $search . '%');
        }

        return $queryBuilder->getQuery()->getResult();
    }

    /**
     * @return list<User>
     */
    public function findForEmailingAudience(string $audience): array
    {
        $queryBuilder = $this->createQueryBuilder('user')
            ->andWhere('user.email != :emptyEmail')
            ->setParameter('emptyEmail', '')
            ->orderBy('user.email', 'ASC');

        if ('active_customers' === $audience) {
            $queryBuilder->andWhere('user.active = true');
        }

        if ('verified_customers' === $audience) {
            $queryBuilder
                ->andWhere('user.active = true')
                ->andWhere('user.verified = true');
        }

        return array_values(array_filter(
            $queryBuilder->getQuery()->getResult(),
            static fn (User $user): bool => !in_array('ROLE_ADMIN', $user->getRoles(), true),
        ));
    }

    /**
     * @param list<int> $ids
     *
     * @return list<User>
     */
    public function findForEmailingSelection(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(
            array_map('intval', $ids),
            static fn (int $id): bool => $id > 0,
        )));

        if ([] === $ids) {
            return [];
        }

        $users = $this->createQueryBuilder('user')
            ->andWhere('user.id IN (:ids)')
            ->andWhere('user.email != :emptyEmail')
            ->setParameter('ids', $ids)
            ->setParameter('emptyEmail', '')
            ->orderBy('user.email', 'ASC')
            ->getQuery()
            ->getResult();

        return array_values(array_filter(
            $users,
            static fn (User $user): bool => !in_array('ROLE_ADMIN', $user->getRoles(), true),
        ));
    }
}
