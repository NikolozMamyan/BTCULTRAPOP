<?php

namespace App\Tests\Controller\Front;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class BlogControllerTest extends WebTestCase
{
    #[DataProvider('editorialArticles')]
    public function testNewEditorialArticlesHaveLargeCoversAndMatchingProductLinks(string $slug, string $universe): void
    {
        $client = static::createClient();
        $this->skipIfBlogIsUnavailable();
        $crawler = $client->request('GET', '/blog/' . $slug);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('link[rel="canonical"][href="https://ultrapop.com/blog/' . $slug . '"]');
        self::assertSelectorExists('.blog-article__cover img[src$=".webp"][width][height]');
        self::assertGreaterThanOrEqual(1600, (int) $crawler->filter('.blog-article__cover img')->attr('width'));
        self::assertSelectorNotExists('.blog-article__image-frame.is-padded');
        self::assertSelectorCount(3, '.blog-product-links li');
        foreach ($crawler->filter('.blog-product-links li') as $product) {
            self::assertStringContainsString($universe, $product->textContent);
        }
        foreach ($crawler->filter('script[type="application/ld+json"]') as $schema) {
            json_decode($schema->textContent, true, 512, JSON_THROW_ON_ERROR);
        }
    }

    public static function editorialArticles(): iterable
    {
        yield ['one-piece-selection-boissons-snacks-fan', 'One Piece'];
        yield ['naruto-preparer-soiree-anime-boissons-snacks', 'Naruto'];
        yield ['dragon-ball-choisir-cadeau-manga', 'Dragon Ball'];
    }

    public function testPublicBlogDisplaysImportedArticles(): void
    {
        $client = static::createClient();
        $this->skipIfBlogIsUnavailable();

        $client->request('GET', '/blog');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('link[rel="canonical"][href$="/blog"]');
        self::assertGreaterThanOrEqual(13, $client->getCrawler()->filter('.blog-featured, .blog-card')->count());
        self::assertSelectorTextContains('h1', 'Le blog ULTRAPOP');
        self::assertSelectorTextContains('.blog-index', 'Demon Slayer: Kimetsu No Yaiba');
        self::assertSelectorTextContains('.blog-index', 'Naruto');
        self::assertSelectorTextContains('.blog-index', 'Jujutsu Kaisen');
        self::assertSelectorNotExists('header nav a[href="/blog"]');
        self::assertSelectorExists('footer a[href="/blog"]');
        self::assertSelectorExists('.blog-card__media.is-padded img[width="1920"]');
        self::assertSelectorExists('a[href="/blog/one-piece-selection-boissons-snacks-fan"]');
        self::assertSelectorExists('a[href="/blog/naruto-preparer-soiree-anime-boissons-snacks"]');
        self::assertSelectorExists('a[href="/blog/dragon-ball-choisir-cadeau-manga"]');
    }

    public function testPublicArticleDisplaysCompleteSanitizedContent(): void
    {
        $client = static::createClient();
        $this->skipIfBlogIsUnavailable();

        $client->request(
            'GET',
            '/blog/demon-slayer-kimetsu-no-yaiba-the-movie-infinity-castle-un-triomphe-annonce',
        );

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Infinity Castle');
        self::assertSelectorExists('meta[property="og:type"][content="article"]');
        self::assertSelectorExists('.blog-article__content img[src*="/assets/img/blog/doma"]');
        self::assertSelectorTextContains('.blog-article__content', 'Sorti le 18 juillet 2025');
        self::assertSelectorNotExists('.blog-article__content script');
        self::assertSelectorNotExists('.blog-article__content [style]');
        self::assertSelectorCount(3, '.blog-product-links a[href^="/boutique/produit/"]');
        self::assertSelectorExists('.blog-article__meta a[href="/notre-histoire#redaction"]');
        self::assertStringContainsString('"@type":"BlogPosting"', $client->getResponse()->getContent());
    }

    private function skipIfBlogIsUnavailable(): void
    {
        try {
            $connection = static::getContainer()->get(Connection::class);
            \assert($connection instanceof Connection);

            if (!$connection->createSchemaManager()->tablesExist(['blog_post'])) {
                self::markTestSkipped('Run Doctrine migrations before testing the blog.');
            }
        } catch (\Throwable $exception) {
            self::markTestSkipped(sprintf('Database connection is unavailable in test env: %s', $exception->getMessage()));
        }
    }
}
