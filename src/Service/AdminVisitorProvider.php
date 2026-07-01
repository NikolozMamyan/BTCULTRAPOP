<?php

namespace App\Service;

use Doctrine\DBAL\Connection;

final readonly class AdminVisitorProvider
{
    private const ONLINE_WINDOW = '-5 minutes';
    private const MAX_ROWS = 200;
    private const STORAGE_TIMEZONE = 'UTC';
    private const DISPLAY_TIMEZONE = 'Europe/Paris';

    public function __construct(private Connection $connection)
    {
    }

    /**
     * @return array{
     *     visitors: list<array<string, mixed>>,
     *     stats: list<array{label: string, value: string, icon: string, tone: string}>,
     *     filter: string,
     *     refreshed_at: \DateTimeImmutable
     * }
     */
    public function online(string $filter = 'humans'): array
    {
        $threshold = new \DateTimeImmutable(self::ONLINE_WINDOW, $this->storageTimezone());
        $allVisitors = array_map(
            fn (array $row): array => $this->presentVisitor($row),
            $this->visitorRows($threshold),
        );
        $visitors = $this->filterVisitors($allVisitors, $filter);

        $customers = count(array_filter($allVisitors, static fn (array $visitor): bool => 'customer' === $visitor['type']));
        $suspects = count(array_filter($allVisitors, static fn (array $visitor): bool => true === $visitor['suspected_bot']));
        $withCart = count(array_filter($allVisitors, static fn (array $visitor): bool => $visitor['cart_quantity'] > 0));
        $humans = count(array_filter($allVisitors, fn (array $visitor): bool => $this->isVisibleHuman($visitor)));

        return [
            'visitors' => $visitors,
            'stats' => [
                [
                    'label' => 'admin.viewer.stats.online',
                    'value' => (string) $humans,
                    'icon' => 'fa-solid fa-signal',
                    'tone' => 'green',
                ],
                [
                    'label' => 'admin.viewer.stats.customers',
                    'value' => (string) $customers,
                    'icon' => 'fa-solid fa-user-check',
                    'tone' => 'blue',
                ],
                [
                    'label' => 'admin.viewer.stats.suspects',
                    'value' => (string) $suspects,
                    'icon' => 'fa-solid fa-robot',
                    'tone' => 'yellow',
                ],
                [
                    'label' => 'admin.viewer.stats.with_cart',
                    'value' => (string) $withCart,
                    'icon' => 'fa-solid fa-cart-shopping',
                    'tone' => 'red',
                ],
            ],
            'filter' => in_array($filter, ['humans', 'all', 'bots'], true) ? $filter : 'humans',
            'refreshed_at' => (new \DateTimeImmutable())->setTimezone($this->displayTimezone()),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function visitorRows(\DateTimeImmutable $threshold): array
    {
        return $this->connection->executeQuery(
            'SELECT
                sv.id,
                sv.visitor_token,
                sv.visitor_type,
                sv.device_name,
                sv.current_path,
                sv.current_route,
                sv.referer,
                sv.first_seen_at,
                sv.last_seen_at,
                sv.hit_count,
                sv.human_score,
                sv.suspected_bot,
                sv.bot_reason,
                sv.user_id,
                u.email,
                u.first_name,
                u.last_name,
                c.id AS cart_id,
                c.status AS cart_status,
                COALESCE(SUM(ci.quantity), 0) AS cart_quantity,
                COALESCE(SUM(ci.quantity * ci.unit_price_tax_included_cents), 0) AS cart_total_cents
            FROM site_visitor sv
            LEFT JOIN app_user u ON u.id = sv.user_id
            LEFT JOIN cart c ON c.id = sv.cart_id
            LEFT JOIN cart_item ci ON ci.cart_id = c.id
            WHERE sv.last_seen_at >= ?
            GROUP BY sv.id, sv.visitor_token, sv.visitor_type, sv.device_name, sv.current_path, sv.current_route, sv.referer, sv.first_seen_at, sv.last_seen_at, sv.hit_count, sv.human_score, sv.suspected_bot, sv.bot_reason, sv.user_id, u.email, u.first_name, u.last_name, c.id, c.status
            ORDER BY sv.last_seen_at DESC, sv.id DESC
            LIMIT ' . self::MAX_ROWS,
            [$threshold->format('Y-m-d H:i:s')],
        )->fetchAllAssociative();
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function presentVisitor(array $row): array
    {
        $lastSeenAt = $this->dateFromDatabase($row['last_seen_at']);

        return [
            'id' => (int) $row['id'],
            'type' => (string) $row['visitor_type'],
            'label' => $this->label($row),
            'email' => trim((string) ($row['email'] ?? '')),
            'session' => $this->shortToken($row['visitor_token']),
            'device' => (string) $row['device_name'],
            'current_path' => (string) $row['current_path'],
            'current_route' => (string) ($row['current_route'] ?? ''),
            'referer' => (string) ($row['referer'] ?? ''),
            'first_seen_at' => $this->dateFromDatabase($row['first_seen_at']),
            'last_seen_at' => $lastSeenAt,
            'last_seen_age' => $this->ageLabel($lastSeenAt),
            'hit_count' => (int) ($row['hit_count'] ?? 0),
            'human_score' => (int) ($row['human_score'] ?? 0),
            'suspected_bot' => (bool) ($row['suspected_bot'] ?? false),
            'bot_reason' => trim((string) ($row['bot_reason'] ?? '')),
            'cart_id' => null === $row['cart_id'] ? null : (int) $row['cart_id'],
            'cart_status' => $row['cart_status'],
            'cart_quantity' => (int) $row['cart_quantity'],
            'cart_total' => $this->formatCents((int) $row['cart_total_cents']),
        ];
    }

    /**
     * @param list<array<string, mixed>> $visitors
     *
     * @return list<array<string, mixed>>
     */
    private function filterVisitors(array $visitors, string $filter): array
    {
        return array_values(array_filter(
            $visitors,
            fn (array $visitor): bool => match ($filter) {
                'all' => true,
                'bots' => true === $visitor['suspected_bot'],
                default => $this->isVisibleHuman($visitor),
            },
        ));
    }

    /**
     * @param array<string, mixed> $visitor
     */
    private function isVisibleHuman(array $visitor): bool
    {
        if (true === $visitor['suspected_bot']) {
            return false;
        }

        return 'customer' === $visitor['type']
            || null !== $visitor['cart_id']
            || (int) $visitor['human_score'] >= 3;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function label(array $row): string
    {
        if (null === $row['user_id']) {
            return 'Invité';
        }

        $name = trim(sprintf('%s %s', (string) ($row['first_name'] ?? ''), (string) ($row['last_name'] ?? '')));

        return '' !== $name ? $name : (string) $row['email'];
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

        if ($seconds < 20) {
            return 'live';
        }

        if ($seconds < 60) {
            return sprintf('%d s', $seconds);
        }

        return sprintf('%d min', (int) floor($seconds / 60));
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
