<?php

namespace App\Tests\Service;

use App\Service\AssetUrlResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Asset\Packages;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class AssetUrlResolverTest extends TestCase
{
    public function testResolveAbsoluteUsesTheCurrentPublicRequestDomain(): void
    {
        $packages = $this->createMock(Packages::class);
        $packages
            ->expects(self::once())
            ->method('getUrl')
            ->with('img/products/164-large_default.jpg')
            ->willReturn('/assets/img/products/164-large_default-hash.jpg');
        $requestStack = new RequestStack();
        $requestStack->push(Request::create('https://preprod.ultrapop.com/checkout/stripe'));

        $resolver = new AssetUrlResolver($packages, $requestStack, 'http://localhost');

        self::assertSame(
            'https://preprod.ultrapop.com/assets/img/products/164-large_default-hash.jpg',
            $resolver->resolveAbsolute('img/products/164-large_default.jpg'),
        );
    }

    public function testResolveAbsoluteUsesDefaultUriOutsideAnHttpRequest(): void
    {
        $packages = $this->createMock(Packages::class);
        $packages
            ->expects(self::once())
            ->method('getUrl')
            ->with('img/products/cover.webp')
            ->willReturn('/assets/img/products/cover-hash.webp');

        $resolver = new AssetUrlResolver(
            $packages,
            new RequestStack(),
            'https://ultrapop.com/application',
        );

        self::assertSame(
            'https://ultrapop.com/assets/img/products/cover-hash.webp',
            $resolver->resolveAbsolute('img/products/cover.webp'),
        );
    }

    public function testResolveAbsoluteKeepsAnExistingHttpsUrl(): void
    {
        $packages = $this->createMock(Packages::class);
        $packages->expects(self::never())->method('getUrl');
        $resolver = new AssetUrlResolver($packages, new RequestStack(), 'https://ultrapop.com');

        self::assertSame(
            'https://cdn.example.com/product.webp',
            $resolver->resolveAbsolute('https://cdn.example.com/product.webp'),
        );
    }
}
