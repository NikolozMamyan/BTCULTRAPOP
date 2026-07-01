<?php

namespace App\Service;

use Doctrine\DBAL\Connection;

final readonly class AdminVisitorProvider
{
    private const ONLINE_WINDOW = '-5 minutes';
    private const MAX_ROWS = 200;

    public function __construct(private Connection $connection)
    {
    }

    /**
     * @return array{
     *     visitors: list<array<string, mixed>>,
     *     stats: list<array{label: string, value: string, icon: string, tone: string}>,
     *     refreshed_at: \DateTimeImmutable
     * }
     */
    public function online(): array
    {
        $threshold = new \DateTimeImmutable(self::ONLINE_WINDOW);
        $visitors = array_map(
            fn (array $row): array => $this->presentVisitor($row),
            $this->visitorRows($threshold),
        );

        $customers = count(array_filter($visitors, static fn (array $visitor): bool => 'customer' === $visitor['type']));
        $guests = count(array_filter($visitors, static fn (array $visitor): bool => 'guest' === $visitor['type']));
        $withCart = count(array_filter($visitors, static fn (array $visitor): bool => $visitor['cart_quantity'] > 0));

        return [
            'visitors' => $visitors,
            'stats' => [
                [
                    'label' => 'admin.viewer.stats.online',
                    'value' => (string) count($visitors),
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
                    'label' => 'admin.viewer.stats.guests',
                    'value' => (string) $guests,
                    'icon' => 'fa-solid fa-user-clock',
                    'tone' => 'yellow',
                ],
                [
                    'label' => 'admin.viewer.stats.with_cart',
                    'value' => (string) $withCart,
                    'icon' => 'fa-solid fa-cart-shopping',
                    'tone' => 'red',
                ],
            ],
            'refreshed_at' => new \DateTimeImmutable(),
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
            GROUP BY sv.id, sv.visitor_token, sv.visitor_type, sv.device_name, sv.current_path, sv.current_route, sv.referer, sv.first_seen_at, sv.last_seen_at, sv.user_id, u.email, u.first_name, u.last_name, c.id, c.status
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
            'cart_id' => null === $row['cart_id'] ? null : (int) $row['cart_id'],
            'cart_status' => $row['cart_status'],
            'cart_quantity' => (int) $row['cart_quantity'],
            'cart_total' => $this->formatCents((int) $row['cart_total_cents']),
        ];
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
            return $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return \DateTimeImmutable::createFromInterface($value);
        }

        return new \DateTimeImmutable((string) $value);
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
}
