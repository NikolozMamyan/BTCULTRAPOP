<?php

namespace App\Service;

use App\Entity\Address;
use App\Entity\Order;
use App\Entity\User;
use App\Repository\OrderRepository;
use App\Repository\UserRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

final readonly class AdminCustomerProvider
{
    public function __construct(
        private UserRepository $users,
        private OrderRepository $orders,
        private Connection $connection,
    ) {
    }

    /**
     * @return array{
     *     customers: list<array<string, mixed>>,
     *     stats: list<array{label: string, value: string, icon: string, tone: string}>
     * }
     */
    public function index(string $search = ''): array
    {
        $users = $this->users->findForAdmin($search);
        $orderStats = $this->orderStatsForUsers($users);

        return [
            'customers' => array_map(
                fn (User $user): array => $this->presentCustomer($user, $orderStats[$user->getId()] ?? []),
                $users,
            ),
            'stats' => [
                [
                    'label' => 'admin.customer.stats.total',
                    'value' => (string) $this->users->count([]),
                    'icon' => 'fa-solid fa-users',
                    'tone' => 'blue',
                ],
                [
                    'label' => 'admin.customer.stats.active',
                    'value' => (string) $this->users->count(['active' => true]),
                    'icon' => 'fa-solid fa-user-check',
                    'tone' => 'green',
                ],
                [
                    'label' => 'admin.customer.stats.verified',
                    'value' => (string) $this->users->count(['verified' => true]),
                    'icon' => 'fa-solid fa-shield-halved',
                    'tone' => 'yellow',
                ],
                [
                    'label' => 'admin.customer.stats.with_orders',
                    'value' => (string) $this->countCustomersWithOrders(),
                    'icon' => 'fa-solid fa-receipt',
                    'tone' => 'red',
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function show(User $user): array
    {
        $stats = $this->orderStatsForUsers([$user])[$user->getId()] ?? [];

        return [
            ...$this->presentCustomer($user, $stats),
            'addresses' => array_map(
                fn (Address $address): array => [
                    'name' => $address->getName(),
                    'street' => $address->getStreet(),
                    'postal_code' => $address->getPostalCode(),
                    'city' => $address->getCity(),
                    'country_code' => $address->getCountryCode(),
                    'phone' => $address->getPhone(),
                    'default' => $address->isDefaultAddress(),
                ],
                $user->getAddresses()->toArray(),
            ),
            'orders' => array_map(
                fn (Order $order): array => [
                    'id' => $order->getId(),
                    'number' => $order->getOrderNumber(),
                    'total' => $this->formatCents($order->getTotalTaxIncludedCents()),
                    'status' => $order->getStatus()->value,
                    'status_key' => 'admin.order.status.' . $order->getStatus()->value,
                    'payment_status' => $order->getPaymentStatus()->value,
                    'payment_status_key' => 'admin.order.payment_status.' . $order->getPaymentStatus()->value,
                    'created_at' => $order->getCreatedAt(),
                ],
                $this->orders->findRecentForUser($user, 10),
            ),
        ];
    }

    /**
     * @param list<User> $users
     *
     * @return array<int, array{order_count: int, total_spent_cents: int}>
     */
    private function orderStatsForUsers(array $users): array
    {
        $ids = array_values(array_filter(
            array_map(static fn (User $user): ?int => $user->getId(), $users),
            static fn (?int $id): bool => null !== $id,
        ));

        if ([] === $ids) {
            return [];
        }

        $rows = $this->connection->executeQuery(
            'SELECT user_id, COUNT(*) AS order_count, COALESCE(SUM(total_tax_included_cents), 0) AS total_spent_cents
            FROM customer_order
            WHERE user_id IN (?)
            GROUP BY user_id',
            [$ids],
            [ArrayParameterType::INTEGER],
        )->fetchAllAssociative();

        $stats = [];

        foreach ($rows as $row) {
            $stats[(int) $row['user_id']] = [
                'order_count' => (int) $row['order_count'],
                'total_spent_cents' => (int) $row['total_spent_cents'],
            ];
        }

        return $stats;
    }

    /**
     * @param array{order_count?: int, total_spent_cents?: int} $orderStats
     *
     * @return array<string, mixed>
     */
    private function presentCustomer(User $user, array $orderStats): array
    {
        $orderCount = $orderStats['order_count'] ?? 0;
        $totalSpentCents = $orderStats['total_spent_cents'] ?? 0;

        return [
            'id' => $user->getId(),
            'name' => $user->getFullName() ?: $user->getEmail(),
            'email' => $user->getEmail(),
            'phone' => $user->getPhone(),
            'active' => $user->isActive(),
            'verified' => $user->isVerified(),
            'admin' => in_array('ROLE_ADMIN', $user->getRoles(), true),
            'loyalty_points' => $user->getLoyaltyPoints(),
            'locale' => $user->getPreferredLocale(),
            'created_at' => $user->getCreatedAt(),
            'last_login_at' => $user->getLastLoginAt(),
            'order_count' => $orderCount,
            'total_spent' => $this->formatCents($totalSpentCents),
            'total_spent_cents' => $totalSpentCents,
        ];
    }

    private function countCustomersWithOrders(): int
    {
        return (int) $this->connection->fetchOne('SELECT COUNT(DISTINCT user_id) FROM customer_order WHERE user_id IS NOT NULL');
    }

    private function formatCents(int $cents): string
    {
        return number_format($cents / 100, 2, ',', ' ') . ' €';
    }
}
