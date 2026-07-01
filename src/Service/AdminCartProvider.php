<?php

namespace App\Service;

use Doctrine\DBAL\Connection;

final readonly class AdminCartProvider
{
    private const ABANDONED_AFTER = '-2 hours';
    private const MAX_ROWS = 200;
    private const STORAGE_TIMEZONE = 'UTC';
    private const DISPLAY_TIMEZONE = 'Europe/Paris';

    public function __construct(private Connection $connection)
    {
    }

    /**
     * @return array{
     *     carts: list<array<string, mixed>>,
     *     stats: list<array{label: string, value: string, icon: string, tone: string}>,
     *     filter: string
     * }
     */
    public function index(string $filter = 'all'): array
    {
        $threshold = new \DateTimeImmutable(self::ABANDONED_AFTER, $this->storageTimezone());
        $carts = array_map(
            fn (array $row): array => $this->presentCart($row, $threshold),
            $this->cartRows(),
        );

        return [
            'carts' => array_values(array_filter(
                $carts,
                fn (array $cart): bool => $this->matchesFilter($cart, $filter),
            )),
            'stats' => $this->stats($threshold),
            'filter' => $filter,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function cartRows(): array
    {
        return $this->connection->executeQuery(
            'SELECT
                c.id,
                c.token,
                c.status,
                c.created_at,
                c.updated_at,
                c.expires_at,
                c.user_id,
                u.email AS user_email,
                u.first_name,
                u.last_name,
                pc.code AS promo_code,
                COUNT(ci.id) AS item_count,
                COALESCE(SUM(ci.quantity), 0) AS total_quantity,
                COALESCE(SUM(ci.quantity * ci.unit_price_tax_included_cents), 0) AS total_cents,
                GROUP_CONCAT(CONCAT(p.name, \' × \', ci.quantity) ORDER BY ci.updated_at DESC SEPARATOR \'||\') AS products,
                MAX(cr.sent_at) AS last_recovery_sent_at,
                COUNT(DISTINCT cr.id) AS recovery_count
            FROM cart c
            LEFT JOIN app_user u ON u.id = c.user_id
            LEFT JOIN promo_code pc ON pc.id = c.promo_code_id
            LEFT JOIN cart_item ci ON ci.cart_id = c.id
            LEFT JOIN product p ON p.id = ci.product_id
            LEFT JOIN cart_recovery cr ON cr.cart_id = c.id
            GROUP BY c.id, c.token, c.status, c.created_at, c.updated_at, c.expires_at, c.user_id, u.email, u.first_name, u.last_name, pc.code
            ORDER BY c.updated_at DESC, c.id DESC
            LIMIT ' . self::MAX_ROWS,
        )->fetchAllAssociative();
    }

    /**
     * @return list<array{label: string, value: string, icon: string, tone: string}>
     */
    private function stats(\DateTimeImmutable $threshold): array
    {
        $rows = $this->connection->executeQuery(
            'SELECT
                c.id,
                c.status,
                c.updated_at,
                COALESCE(SUM(ci.quantity), 0) AS total_quantity,
                COALESCE(SUM(ci.quantity * ci.unit_price_tax_included_cents), 0) AS total_cents
            FROM cart c
            LEFT JOIN cart_item ci ON ci.cart_id = c.id
            GROUP BY c.id, c.status, c.updated_at',
        )->fetchAllAssociative();

        $active = 0;
        $abandoned = 0;
        $converted = 0;
        $tracked = 0;
        $abandonedValue = 0;

        foreach ($rows as $row) {
            $quantity = (int) $row['total_quantity'];

            if ($quantity <= 0) {
                continue;
            }

            ++$tracked;
            $status = (string) $row['status'];
            $updatedAt = $this->dateFromDatabase($row['updated_at']);
            $isAbandoned = 'abandoned' === $status || ('active' === $status && $updatedAt <= $threshold);

            if ('converted' === $status) {
                ++$converted;
                continue;
            }

            if ($isAbandoned) {
                ++$abandoned;
                $abandonedValue += (int) $row['total_cents'];
                continue;
            }

            if ('active' === $status) {
                ++$active;
            }
        }

        $conversionRate = $tracked > 0 ? round(($converted / $tracked) * 100, 1) : 0;

        return [
            [
                'label' => 'admin.cart.stats.active',
                'value' => (string) $active,
                'icon' => 'fa-solid fa-basket-shopping',
                'tone' => 'blue',
            ],
            [
                'label' => 'admin.cart.stats.abandoned',
                'value' => (string) $abandoned,
                'icon' => 'fa-solid fa-cart-arrow-down',
                'tone' => 'red',
            ],
            [
                'label' => 'admin.cart.stats.converted',
                'value' => (string) $converted,
                'icon' => 'fa-solid fa-circle-check',
                'tone' => 'green',
            ],
            [
                'label' => 'admin.cart.stats.conversion',
                'value' => number_format($conversionRate, 1, ',', ' ') . ' %',
                'icon' => 'fa-solid fa-chart-line',
                'tone' => 'yellow',
            ],
            [
                'label' => 'admin.cart.stats.abandoned_value',
                'value' => $this->formatCents($abandonedValue),
                'icon' => 'fa-solid fa-euro-sign',
                'tone' => 'red',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function presentCart(array $row, \DateTimeImmutable $threshold): array
    {
        $updatedAt = $this->dateFromDatabase($row['updated_at']);
        $status = (string) $row['status'];
        $quantity = (int) $row['total_quantity'];
        $isCustomer = null !== $row['user_id'];
        $isAbandoned = 'abandoned' === $status || ('active' === $status && $quantity > 0 && $updatedAt <= $threshold);
        $email = trim((string) ($row['user_email'] ?? ''));
        $lastRecoverySentAt = null !== $row['last_recovery_sent_at'] ? $this->dateFromDatabase($row['last_recovery_sent_at']) : null;
        $canRecover = $isAbandoned
            && '' !== $email
            && $quantity > 0
            && (null === $lastRecoverySentAt || $lastRecoverySentAt <= new \DateTimeImmutable('-24 hours'));

        return [
            'id' => (int) $row['id'],
            'owner_type' => $isCustomer ? 'customer' : 'guest',
            'owner_label' => $this->ownerLabel($row),
            'email' => $email,
            'session' => $this->shortToken($row['token'] ?? null),
            'status' => $status,
            'status_key' => $this->statusKey($status, $isAbandoned, $quantity),
            'is_abandoned' => $isAbandoned,
            'item_count' => (int) $row['item_count'],
            'total_quantity' => $quantity,
            'total' => $this->formatCents((int) $row['total_cents']),
            'total_cents' => (int) $row['total_cents'],
            'promo_code' => $row['promo_code'],
            'products' => $this->products((string) ($row['products'] ?? '')),
            'created_at' => $this->dateFromDatabase($row['created_at']),
            'updated_at' => $updatedAt,
            'age' => $this->ageLabel($updatedAt),
            'recovery_count' => (int) $row['recovery_count'],
            'last_recovery_sent_at' => $lastRecoverySentAt,
            'can_recover' => $canRecover,
            'recovery_block_key' => $this->recoveryBlockKey($isAbandoned, $email, $quantity, $lastRecoverySentAt),
        ];
    }

    /**
     * @param array<string, mixed> $cart
     */
    private function matchesFilter(array $cart, string $filter): bool
    {
        return match ($filter) {
            'active' => 'active' === $cart['status'] && !$cart['is_abandoned'] && $cart['total_quantity'] > 0,
            'abandoned' => true === $cart['is_abandoned'],
            'converted' => 'converted' === $cart['status'],
            'customers' => 'customer' === $cart['owner_type'],
            'guests' => 'guest' === $cart['owner_type'],
            default => true,
        };
    }

    /**
     * @param array<string, mixed> $row
     */
    private function ownerLabel(array $row): string
    {
        if (null === $row['user_id']) {
            return 'Invité';
        }

        $name = trim(sprintf('%s %s', (string) ($row['first_name'] ?? ''), (string) ($row['last_name'] ?? '')));

        return '' !== $name ? $name : (string) $row['user_email'];
    }

    private function statusKey(string $status, bool $isAbandoned, int $quantity): string
    {
        if (0 === $quantity) {
            return 'admin.cart.status.empty';
        }

        if ($isAbandoned && 'abandoned' !== $status) {
            return 'admin.cart.status.abandoned_candidate';
        }

        return 'admin.cart.status.' . $status;
    }

    private function recoveryBlockKey(bool $isAbandoned, string $email, int $quantity, ?\DateTimeImmutable $lastRecoverySentAt): string
    {
        if ($quantity <= 0) {
            return 'admin.cart.recovery.block.empty';
        }

        if (!$isAbandoned) {
            return 'admin.cart.recovery.block.not_abandoned';
        }

        if ('' === $email) {
            return 'admin.cart.recovery.block.no_email';
        }

        if (null !== $lastRecoverySentAt && $lastRecoverySentAt > new \DateTimeImmutable('-24 hours')) {
            return 'admin.cart.recovery.block.recent';
        }

        return 'admin.cart.recovery.block.ready';
    }

    /**
     * @return list<string>
     */
    private function products(string $products): array
    {
        if ('' === trim($products)) {
            return [];
        }

        return array_slice(array_values(array_filter(explode('||', $products))), 0, 4);
    }

    private function dateFromDatabase(mixed $value): \DateTimeImmutable
    {
        if ($value instanceof \DateTimeImmutable) {
            return $value->setTimezone($this->displayTimezone());
        }

        if ($value instanceof \DateTimeInterface) {
            return \DateTimeImmutable::createFromInterface($value)->setTimezone($this->displayTimezone());
        }

        return (new \DateTimeImmutable((string) $value, $this->storageTimezone()))->setTimezone($this->displayTimezone());
    }

    private function shortToken(mixed $token): string
    {
        $token = trim((string) $token);

        if ('' === $token) {
            return '—';
        }

        return substr($token, 0, 8) . '…' . substr($token, -4);
    }

    private function ageLabel(\DateTimeImmutable $date): string
    {
        $seconds = max(0, (new \DateTimeImmutable())->getTimestamp() - $date->getTimestamp());

        if ($seconds < 60) {
            return 'maintenant';
        }

        if ($seconds < 3600) {
            return sprintf('%d min', (int) floor($seconds / 60));
        }

        if ($seconds < 86400) {
            return sprintf('%d h', (int) floor($seconds / 3600));
        }

        return sprintf('%d j', (int) floor($seconds / 86400));
    }

    private function formatCents(int $cents): string
    {
        return number_format($cents / 100, 2, ',', ' ') . ' €';
    }

    private function displayTimezone(): \DateTimeZone
    {
        return new \DateTimeZone(self::DISPLAY_TIMEZONE);
    }

    private function storageTimezone(): \DateTimeZone
    {
        return new \DateTimeZone(self::STORAGE_TIMEZONE);
    }
}
