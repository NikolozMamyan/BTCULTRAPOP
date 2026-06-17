<?php

namespace App\Service;

use Doctrine\DBAL\Connection;

final readonly class AdminDashboardProvider
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function dashboard(): array
    {
        return [
            'stats' => [
                [
                    'label' => 'admin.dashboard.stats.products',
                    'value' => $this->countRows('product'),
                    'icon' => 'fa-solid fa-box-open',
                    'tone' => 'blue',
                ],
                [
                    'label' => 'admin.dashboard.stats.users',
                    'value' => $this->countRows('app_user'),
                    'icon' => 'fa-solid fa-users',
                    'tone' => 'red',
                ],
                [
                    'label' => 'admin.dashboard.stats.orders',
                    'value' => $this->countRows('customer_order'),
                    'icon' => 'fa-solid fa-receipt',
                    'tone' => 'yellow',
                ],
                [
                    'label' => 'admin.dashboard.stats.favorites',
                    'value' => $this->countRows('user_favorite'),
                    'icon' => 'fa-solid fa-heart',
                    'tone' => 'green',
                ],
            ],
            'revenue' => $this->formatCurrencyCents((int) $this->connection->fetchOne('SELECT COALESCE(SUM(total_tax_included_cents), 0) FROM customer_order')),
            'pending_orders' => (int) $this->connection->fetchOne("SELECT COUNT(*) FROM customer_order WHERE status = 'pending_payment'"),
            'low_stock_products' => (int) $this->connection->fetchOne('SELECT COUNT(*) FROM product WHERE quantity <= 5'),
            'recent_orders' => $this->recentOrders(),
        ];
    }

    private function countRows(string $table): int
    {
        $table = match ($table) {
            'product', 'app_user', 'customer_order', 'user_favorite' => $table,
            default => throw new \InvalidArgumentException(sprintf('Unsupported admin table "%s".', $table)),
        };

        return (int) $this->connection->fetchOne(sprintf('SELECT COUNT(*) FROM %s', $table));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recentOrders(): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT order_number, customer_name, total_tax_included_cents, status, created_at FROM customer_order ORDER BY created_at DESC LIMIT 5',
        );

        return array_map(
            fn (array $row): array => [
                'number' => (string) $row['order_number'],
                'customer' => (string) $row['customer_name'],
                'total' => $this->formatCurrencyCents((int) $row['total_tax_included_cents']),
                'status' => (string) $row['status'],
                'created_at' => (string) $row['created_at'],
            ],
            $rows,
        );
    }

    private function formatCurrencyCents(int $cents): string
    {
        return number_format($cents / 100, 2, ',', ' ') . ' €';
    }
}
