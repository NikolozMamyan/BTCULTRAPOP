<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261019110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Publish three original manga shopping and lifestyle guides with editorial cover images.';
    }

    public function up(Schema $schema): void
    {
        foreach (require __DIR__ . '/data/blog_posts_20261019.php' as $post) {
            $this->addSql(
                'INSERT INTO blog_post (legacy_id, title, slug, excerpt, content, cover_image, category, seo_title, seo_description, featured, published, published_at, created_at, updated_at)
                 SELECT NULL, :title, :slug, :excerpt, :content, :cover_image, :category, :seo_title, :seo_description, :featured, 1, :published_at, :created_at, :updated_at
                 WHERE NOT EXISTS (SELECT 1 FROM blog_post WHERE slug = :existing_slug)',
                [
                    'title' => $post['title'],
                    'slug' => $post['slug'],
                    'excerpt' => $post['excerpt'],
                    'content' => $post['content'],
                    'cover_image' => $post['cover_image'],
                    'category' => $post['category'],
                    'seo_title' => $post['seo_title'],
                    'seo_description' => $post['seo_description'],
                    'featured' => $post['featured'] ? 1 : 0,
                    'published_at' => $post['published_at'],
                    'created_at' => $post['published_at'],
                    'updated_at' => $post['published_at'],
                    'existing_slug' => $post['slug'],
                ],
            );
        }
    }

    public function down(Schema $schema): void
    {
        foreach (require __DIR__ . '/data/blog_posts_20261019.php' as $post) {
            $this->addSql(
                'DELETE FROM blog_post WHERE slug = :slug AND legacy_id IS NULL AND content = :content AND created_at = :created_at',
                ['slug' => $post['slug'], 'content' => $post['content'], 'created_at' => $post['published_at']],
            );
        }
    }
}
