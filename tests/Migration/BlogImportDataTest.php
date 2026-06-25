<?php

namespace App\Tests\Migration;

use PHPUnit\Framework\TestCase;

final class BlogImportDataTest extends TestCase
{
    public function testLegacyBlogExportContainsCompleteSanitizedFrenchArticles(): void
    {
        /** @var list<array<string, mixed>> $posts */
        $posts = require dirname(__DIR__, 2) . '/migrations/data/blog_posts.php';
        $titles = array_column($posts, 'title');

        self::assertCount(11, $posts);
        self::assertContains(
            'Demon Slayer: Kimetsu No Yaiba – The Movie: Infinity Castle : un triomphe annoncé',
            $titles,
        );
        self::assertContains('Naruto : l’évolution d’un héros de rejeté à légende', $titles);
        self::assertContains('Jujutsu Kaisen : un nouveau souffle pour le shōnen moderne', $titles);

        foreach ($posts as $post) {
            self::assertNotEmpty($post['content']);
            self::assertStringStartsWith('img/blog/', $post['cover_image']);
            self::assertStringNotContainsString('<script', mb_strtolower($post['content']));
            self::assertStringNotContainsString(' style=', mb_strtolower($post['content']));
            self::assertStringNotContainsString(' class=', mb_strtolower($post['content']));
            self::assertDoesNotMatchRegularExpression('/(?:Ã|Â|â€™|â€“|â€”|Å)/u', $post['title']);
            self::assertDoesNotMatchRegularExpression('/(?:Ã|Â|â€™|â€“|â€”|Å)/u', $post['content']);
        }
    }
}
