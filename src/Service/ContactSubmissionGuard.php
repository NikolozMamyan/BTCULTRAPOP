<?php

namespace App\Service;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class ContactSubmissionGuard
{
    private const COOLDOWN_SECONDS = 30;

    public function __construct(
        #[Autowire(service: 'cache.app')]
        private CacheItemPoolInterface $cache,
    ) {
    }

    public function accept(?string $identifier): bool
    {
        $key = 'contact_submission_' . hash('sha256', trim((string) $identifier) ?: 'unknown');
        $item = $this->cache->getItem($key);

        if ($item->isHit()) {
            return false;
        }

        $item->set(true);
        $item->expiresAfter(self::COOLDOWN_SECONDS);
        $this->cache->save($item);

        return true;
    }
}
