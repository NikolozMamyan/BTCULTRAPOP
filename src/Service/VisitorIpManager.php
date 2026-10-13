<?php

namespace App\Service;

use App\Entity\User;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DbalException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final readonly class VisitorIpManager
{
    private const CACHE_KEY = 'visitor_blocked_ips';

    public function __construct(
        private Connection $connection,
        #[Autowire(service: 'cache.app')]
        private CacheInterface $cache,
    ) {
    }

    public static function normalize(string $ip): string
    {
        $ip = trim($ip);

        if (false === filter_var($ip, FILTER_VALIDATE_IP)) {
            throw new \InvalidArgumentException('admin.viewer.ip.invalid');
        }

        return inet_ntop(inet_pton($ip));
    }

    public function isBlocked(?string $ip): bool
    {
        if (null === $ip) {
            return false;
        }

        $ip = self::normalize($ip);
        $blocked = $this->cache->get(self::CACHE_KEY, function (ItemInterface $item): array {
            $item->expiresAfter(30);

            try {
                return array_fill_keys($this->connection->fetchFirstColumn('SELECT ip_address FROM visitor_ip_block'), true);
            } catch (DbalException) {
                return [];
            }
        }, 0);

        return isset($blocked[$ip]);
    }

    public function change(string $ip, string $action, User $admin, ?string $currentIp): void
    {
        $ip = self::normalize($ip);

        if (!in_array($action, ['block', 'unblock'], true)) {
            throw new \InvalidArgumentException('admin.viewer.ip.invalid_action');
        }

        if ('block' === $action && null !== $currentIp && $ip === self::normalize($currentIp)) {
            throw new \InvalidArgumentException('admin.viewer.ip.self_block');
        }

        $this->connection->transactional(function () use ($ip, $action, $admin): void {
            $now = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
            $changed = 'block' === $action
                ? $this->connection->executeStatement(
                    'INSERT INTO visitor_ip_block (ip_address, blocked_at, blocked_by_id) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE ip_address = VALUES(ip_address)',
                    [$ip, $now, $admin->getId()],
                )
                : $this->connection->delete('visitor_ip_block', ['ip_address' => $ip]);

            if ($changed > 0) {
                $this->connection->insert('site_visitor_event', [
                    'ip_address' => $ip,
                    'event_type' => 'block' === $action ? 'ip_blocked' : 'ip_unblocked',
                    'request_path' => '',
                    'route_name' => null,
                    'request_method' => 'ADMIN',
                    'http_status' => null,
                    'actor_id' => $admin->getId(),
                    'occurred_at' => $now,
                ]);
            }
        });

        $this->cache->delete(self::CACHE_KEY);
    }
}
