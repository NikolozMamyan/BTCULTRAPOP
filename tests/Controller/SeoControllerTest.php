<?php

namespace App\Tests\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SeoControllerTest extends WebTestCase
{
    public function testProductionRobotsAllowsPublicPagesAndDeclaresSitemap(): void
    {
        $client = static::createClient([], ['HTTP_HOST' => 'ultrapop.com', 'HTTPS' => 'on']);
        $client->request('GET', '/robots.txt');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'text/plain; charset=UTF-8');
        self::assertStringContainsString("User-agent: *\nAllow: /", (string) $client->getResponse()->getContent());
        self::assertStringContainsString('Disallow: /admin/', (string) $client->getResponse()->getContent());
        self::assertStringContainsString('Sitemap: https://ultrapop.com/sitemap.xml', (string) $client->getResponse()->getContent());
    }

    public function testPreproductionRobotsBlocksAllCrawlers(): void
    {
        $client = static::createClient([], ['HTTP_HOST' => 'preprod.ultrapop.com', 'HTTPS' => 'on']);
        $client->request('GET', '/robots.txt');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString("User-agent: *\nDisallow: /", (string) $client->getResponse()->getContent());
        self::assertResponseHeaderSame('x-robots-tag', 'noindex, nofollow, noarchive');
    }

    public function testSitemapAndLlmsExposeCanonicalProductUrls(): void
    {
        $client = static::createClient([], ['HTTP_HOST' => 'ultrapop.com', 'HTTPS' => 'on']);
        $this->skipIfCatalogIsUnavailable();

        $client->request('GET', '/sitemap.xml');
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'application/xml; charset=UTF-8');
        self::assertStringContainsString(
            'https://ultrapop.com/boutique/produit/1648-ultrapop-dragon-ball-z-vegeta-ice-tea-peche-33cl',
            (string) $client->getResponse()->getContent(),
        );
        self::assertStringContainsString('<image:image>', (string) $client->getResponse()->getContent());

        $client->request('GET', '/llms.txt');
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'text/plain; charset=UTF-8');
        self::assertStringContainsString('# ULTRAPOP', (string) $client->getResponse()->getContent());
        self::assertStringContainsString('URL du sitemap XML', (string) $client->getResponse()->getContent());
    }

    private function skipIfCatalogIsUnavailable(): void
    {
        try {
            $connection = static::getContainer()->get(Connection::class);
            \assert($connection instanceof Connection);

            if ((int) $connection->fetchOne('SELECT COUNT(*) FROM product WHERE id = 1648') < 1) {
                self::markTestSkipped('Storefront product catalog is not loaded in the test database.');
            }
        } catch (\Throwable $exception) {
            self::markTestSkipped(sprintf('Database connection is unavailable: %s', $exception->getMessage()));
        }
    }
}
