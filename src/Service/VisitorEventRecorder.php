<?php

namespace App\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DbalException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final readonly class VisitorEventRecorder
{
    public const TRACK_ATTRIBUTE = '_visitor_event_enabled';
    public const TYPE_ATTRIBUTE = '_visitor_event_type';
    public const RETENTION_DAYS = 30;

    public function __construct(
        private Connection $connection,
        #[Autowire(service: 'cache.app')]
        private CacheInterface $cache,
    ) {
    }

    public function record(Request $request, Response $response): void
    {
        if (!$request->attributes->get(self::TRACK_ATTRIBUTE) || null === $request->getClientIp()) {
            return;
        }

        $type = $request->attributes->get(self::TYPE_ATTRIBUTE);
        $path = $request->getPathInfo();

        if (null === $type && ('/admin' === $path || str_starts_with($path, '/admin/'))) {
            return;
        }

        $type ??= 'app_bot_challenge' === $request->attributes->get('_route')
            ? 'captcha_shown'
            : (str_starts_with($path, '/api/') ? 'api_request' : 'request');
        $ip = VisitorIpManager::normalize($request->getClientIp());
        $row = [
            'ip_address' => $ip,
            'event_type' => $type,
            'request_path' => mb_substr(preg_replace('#(?<=/auth/reinitialiser-mot-de-passe/)[^/]+|[a-f0-9]{32}\.[a-f0-9]{64}#i', '[token]', $path), 0, 255),
            'route_name' => mb_substr((string) $request->attributes->get('_route', ''), 0, 120) ?: null,
            'request_method' => $request->getMethod(),
            'http_status' => $response->getStatusCode(),
            'actor_id' => null,
            'occurred_at' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s'),
        ];

        if (in_array($type, ['captcha_required', 'rate_limited', 'blocked_request'], true)) {
            $this->cache->get('visitor_event_' . hash('sha256', $ip . '|' . $type), function (ItemInterface $item) use ($row): bool {
                $item->expiresAfter(60);

                return $this->insert($row);
            }, 0);

            return;
        }

        $this->insert($row);
    }

    private function insert(array $row): bool
    {
        try {
            $this->connection->insert('site_visitor_event', $row);

            return true;
        } catch (DbalException) {
            return false;
        }
    }
}
