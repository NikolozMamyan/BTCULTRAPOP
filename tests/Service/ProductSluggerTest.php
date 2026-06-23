<?php

namespace App\Tests\Service;

use App\Service\ProductSlugger;
use PHPUnit\Framework\TestCase;

final class ProductSluggerTest extends TestCase
{
    public function testItBuildsAStableAsciiProductSlug(): void
    {
        self::assertSame(
            'ultrapop-dragon-ball-z-vegeta-ice-tea-peche-33cl',
            (new ProductSlugger())->slug('ULTRAPOP - Dragon Ball Z - Vegeta - Ice tea Pêche 33cl'),
        );
    }

    public function testItProvidesAFallbackForAnEmptyName(): void
    {
        self::assertSame('produit-ultrapop', (new ProductSlugger())->slug(''));
    }
}
