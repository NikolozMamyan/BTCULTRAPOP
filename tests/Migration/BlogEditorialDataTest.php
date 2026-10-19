<?php

namespace App\Tests\Migration;

use PHPUnit\Framework\TestCase;

final class BlogEditorialDataTest extends TestCase
{
    public function testNewArticlesHaveCompleteContentAndProjectCoverImages(): void
    {
        $root = dirname(__DIR__, 2);
        $posts = require $root . '/migrations/data/blog_posts_20261019.php';
        self::assertCount(3, $posts);
        self::assertCount(3, array_unique(array_column($posts, 'slug')));

        foreach ($posts as $post) {
            self::assertGreaterThanOrEqual(550, str_word_count(strip_tags($post['content'])));
            self::assertGreaterThanOrEqual(2, substr_count($post['content'], 'href="/boutique/produit/'));
            self::assertStringContainsString('href="/licences/', $post['content']);
            self::assertStringNotContainsString('<script', $post['content']);
            self::assertSame('2026-10-19 00:00:00', $post['published_at']);
            self::assertLessThanOrEqual(320, mb_strlen($post['seo_description']));
            $image = getimagesize($root . '/assets/' . $post['cover_image']);
            self::assertNotFalse($image);
            self::assertGreaterThanOrEqual(1600, $image[0]);
            self::assertSame('image/webp', $image['mime']);
        }
    }
}
