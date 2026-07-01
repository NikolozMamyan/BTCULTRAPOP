<?php

namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

final readonly class AdminCustomerManager
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function save(User $customer, bool $adminRole, User $currentAdmin): void
    {
        $isCurrentAdmin = null !== $customer->getId() && $customer->getId() === $currentAdmin->getId();

        if ($isCurrentAdmin) {
            $adminRole = true;
            $customer->setActive(true);
        }

        $roles = array_values(array_filter(
            $customer->getRoles(),
            static fn (string $role): bool => !in_array($role, ['ROLE_USER', 'ROLE_ADMIN'], true),
        ));

        if ($adminRole) {
            $roles[] = 'ROLE_ADMIN';
        }

        $customer->setRoles($roles);

        $this->entityManager->flush();
    }
}
