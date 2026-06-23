<?php

namespace App\Service;

final class SeoIndexingPolicy
{
    private const INDEXABLE_HOSTS = [
        'ultrapop.com',
        'www.ultrapop.com',
    ];

    public function isIndexableHost(string $host): bool
    {
        return in_array(mb_strtolower(trim($host)), self::INDEXABLE_HOSTS, true);
    }
}
