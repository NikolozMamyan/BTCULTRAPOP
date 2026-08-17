<?php

namespace App\Tests\Service;

use App\Service\StorefrontReturnUrlResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;

final class StorefrontReturnUrlResolverTest extends TestCase
{
    public function testItKeepsAResolvedStorefrontUrl(): void
    {
        $resolver = new StorefrontReturnUrlResolver($this->routerWithRoutes());

        self::assertSame('/licences?license=Naruto#catalogue', $resolver->sanitize('/licences?license=Naruto#catalogue'));
    }

    public function testItRejectsExternalAndProtocolRelativeUrls(): void
    {
        $router = $this->createMock(RouterInterface::class);
        $router->expects(self::never())->method('getRouteCollection');
        $resolver = new StorefrontReturnUrlResolver($router);

        self::assertSame('', $resolver->sanitize('https://example.com/phishing'));
        self::assertSame('', $resolver->sanitize('//example.com/phishing'));
        self::assertSame('', $resolver->sanitize('/\\example.com/phishing'));
    }

    public function testItRejectsUnknownAndBackOfficeRoutes(): void
    {
        $resolver = new StorefrontReturnUrlResolver($this->routerWithRoutes());

        self::assertSame('', $resolver->sanitize('/introuvable'));
        self::assertSame('', $resolver->sanitize('/admin/dashboard'));
    }

    private function routerWithRoutes(): RouterInterface
    {
        $routes = new RouteCollection();
        $routes->add('app_front_licences', new Route('/licences', methods: ['GET']));
        $routes->add('app_admin_dashboard', new Route('/admin/dashboard', methods: ['GET']));

        $router = $this->createStub(RouterInterface::class);
        $router->method('getContext')->willReturn(new RequestContext());
        $router->method('getRouteCollection')->willReturn($routes);

        return $router;
    }
}
