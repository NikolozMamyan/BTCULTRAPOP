<?php

namespace App\Tests\Service;

use App\Service\SeoIndexingPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SeoIndexingPolicyTest extends TestCase
{
    #[DataProvider('provideHosts')]
    public function testOnlyProductionHostsAreIndexable(string $host, bool $expected): void
    {
        self::assertSame($expected, (new SeoIndexingPolicy())->isIndexableHost($host));
    }

    public static function provideHosts(): iterable
    {
        yield 'production' => ['ultrapop.com', true];
        yield 'production www' => ['www.ultrapop.com', true];
        yield 'preproduction' => ['preprod.ultrapop.com', false];
        yield 'local' => ['localhost', false];
    }
}
