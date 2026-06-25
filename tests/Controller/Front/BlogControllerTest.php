<?php

namespace App\Tests\Controller\Front;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class BlogControllerTest extends WebTestCase
{
    public function testPublicBlogDisplaysImportedArticles(): void
    {
        $client = static::createClient();
        $this->skipIfBlogIsUnavailable();

        $client->request('GET', '/blog');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('link[rel="canonical"][href$="/blog"]');
        self::assertSelectorCount(11, '.blog-featured, .blog-card');
        self::assertSelectorTextContains('h1', 'Le blog ULTRAPOP');
        self::assertSelectorTextContains('.blog-index', 'Demon Slayer: Kimetsu No Yaiba');
        self::assertSelectorTextContains('.blog-index', 'Naruto');
        self::assertSelectorTextContains('.blog-index', 'Jujutsu Kaisen');
        self::assertSelectorExists('header a[href="/blog"]');
        self::assertSelectorExists('footer a[href="/blog"]');
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
