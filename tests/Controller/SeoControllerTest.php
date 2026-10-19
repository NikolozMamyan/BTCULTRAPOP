<?php

namespace App\Tests\Controller;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SeoControllerTest extends WebTestCase
{
    public function testWwwRedirectPreservesThePathAndQueryString(): void
    {
        $client = static::createClient([], ['HTTP_HOST' => 'www.ultrapop.com', 'HTTPS' => 'on']);
        $client->request('GET', '/boutique?category=Boissons');

        self::assertResponseRedirects('https://ultrapop.com/boutique?category=Boissons', 301);
    }

    public function testWwwPostRedirectPreservesTheRequestMethod(): void
    {
        $client = static::createClient([], ['HTTP_HOST' => 'www.ultrapop.com', 'HTTPS' => 'on']);
        $client->request('POST', '/api/cart/items');

        self::assertResponseRedirects('https://ultrapop.com/api/cart/items', 308);
    }

    #[DataProvider('missingPages')]
    public function testMissingPagesReturn404(string $path): void
    {
        $client = static::createClient([], ['HTTP_HOST' => 'ultrapop.com', 'HTTPS' => 'on']);
        $client->request('GET', $path);

        self::assertResponseStatusCodeSame(404);
        self::assertFalse($client->getResponse()->headers->has('Location'));
    }

    public static function missingPages(): iterable
    {
        yield ['/doesnotexist'];
        yield ['/llms-full.txt'];
        yield ['/.well-known/llms.txt'];
        yield ['/api/doesnotexist'];
    }

    public function testCustom404PageIsNotIndexable(): void
    {
        $client = static::createClient(['debug' => false], ['HTTP_HOST' => 'ultrapop.com', 'HTTPS' => 'on']);
        $this->skipIfCatalogIsUnavailable();
        $client->request('GET', '/doesnotexist');

        self::assertResponseStatusCodeSame(404);
        self::assertSelectorTextContains('h1', 'Page introuvable');
        self::assertSelectorExists('meta[name="robots"][content="noindex, follow"]');
        self::assertSelectorNotExists('link[rel="canonical"]');
    }

    public function testPublicPagesHaveCanonicalUrlsAndCompiledStyles(): void
    {
        $client = static::createClient([], ['HTTP_HOST' => 'ultrapop.com', 'HTTPS' => 'on']);
        $this->skipIfCatalogIsUnavailable();

        foreach (['/', '/licences', '/notre-histoire', '/faq'] as $path) {
            $crawler = $client->request('GET', $path);
            self::assertResponseIsSuccessful();
            self::assertSelectorExists('link[rel="canonical"][href="https://ultrapop.com' . $path . '"]');
            self::assertSelectorNotExists('script[src*="cdn.tailwindcss.com"]');
            self::assertSelectorExists('link[rel="stylesheet"][href*="site.min-"]');
            self::assertSelectorNotExists('img[src=""]');
            self::assertSelectorNotExists('h2#product-preview-title');
            self::assertSelectorNotExists('header nav a[href="/blog"]');
            self::assertSelectorNotExists('header nav a[href="/notre-histoire"]');
            self::assertLessThan(8, $crawler->filter('link[rel="modulepreload"]')->count());
            foreach ($crawler->filter('link[rel="modulepreload"]') as $preload) {
                self::assertDoesNotMatchRegularExpression('/three|admin_/', $preload->getAttribute('href'));
            }
            $schemas = $crawler->filter('script[type="application/ld+json"]')->each(static fn ($node) => json_decode($node->text(), true, 512, JSON_THROW_ON_ERROR));
            self::assertSame('LNS TRADE', $schemas[0]['@graph'][1]['name']);
        }
    }

    public function testCategoryAndLicensePagesAreLinkedAndRenderedOnTheServer(): void
    {
        $client = static::createClient();
        $this->skipIfCatalogIsUnavailable();
        $client->request('GET', '/boutique');
        self::assertSelectorExists('a.shop-category-button[href="/boutique/boissons"]');
        self::assertSelectorExists('a.shop-category-button[href="/boutique/ultra-ice-tea"]');

        foreach (['/boutique/boissons', '/licences/one-piece'] as $path) {
            $crawler = $client->request('GET', $path);
            self::assertResponseIsSuccessful();
            self::assertGreaterThan(0, $crawler->filter('.shop-product-card')->count());
            self::assertGreaterThan(0, $crawler->filter('.shop-product-card:not([hidden])')->count());
            self::assertSelectorExists('link[rel="canonical"][href="https://ultrapop.com' . $path . '"]');
            self::assertSelectorExists('.shop-sidebar .shop-category-list');
            self::assertSelectorExists('a.shop-category-button[data-action="shop-filters#selectCategory"]');
            foreach ($crawler->filter('.shop-product-card:not([hidden])') as $product) {
                if (str_starts_with($path, '/licences/')) {
                    self::assertSame('One Piece', $product->getAttribute('data-license'));
                } else {
                    self::assertStringContainsString('Boissons', $product->getAttribute('data-category-path'));
                }
            }
        }
        $client->request('GET', '/boutique/categorie-inexistante');
        self::assertResponseStatusCodeSame(404);
    }

    public function testProductPageHasVisibleFaqAndLinksToItsTaxonomies(): void
    {
        $client = static::createClient();
        $this->skipIfCatalogIsUnavailable();
        $crawler = $client->request('GET', '/boutique/produit/1648-ultrapop-dragon-ball-z-vegeta-ice-tea-peche-33cl');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.product-faq details');
        self::assertSelectorExists('.product-tab-copy a[href="/boutique/ultra-ice-tea"]');
        self::assertSelectorExists('.product-tab-copy a[href="/licences/dragon-ball-z"]');
        self::assertStringContainsString('"@type":"FAQPage"', $crawler->html());
        foreach ($crawler->filter('script[type="application/ld+json"]') as $schema) {
            json_decode($schema->textContent, true, 512, JSON_THROW_ON_ERROR);
        }
    }

    public function testRetiredArticleRedirectsToItsLicense(): void
    {
        $client = static::createClient();
        $client->request('GET', '/blog/le-mystere-du-one-piece-et-si-la-plus-grande-theorie-etait-vraie');
        self::assertResponseRedirects('/licences/one-piece', 301);
    }

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
        self::assertStringContainsString('https://ultrapop.com/boutique/boissons', (string) $client->getResponse()->getContent());
        self::assertStringContainsString('https://ultrapop.com/licences/one-piece', (string) $client->getResponse()->getContent());
        self::assertStringNotContainsString('le-mystere-du-one-piece', (string) $client->getResponse()->getContent());

        $client->request('GET', '/llms.txt');
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'text/plain; charset=UTF-8');
        self::assertStringContainsString('# ULTRAPOP', (string) $client->getResponse()->getContent());
        self::assertStringContainsString('URL du sitemap XML', (string) $client->getResponse()->getContent());
        $content = (string) $client->getResponse()->getContent();
        self::assertGreaterThan(1, preg_match_all('/^- \[.*\]\(https:\/\/ultrapop\.com\/boutique\/produit\//m', $content));
        foreach (explode("\n", $content) as $line) {
            self::assertLessThanOrEqual(1, substr_count($line, 'https://ultrapop.com/boutique/produit/'));
        }
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
