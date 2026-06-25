<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260625110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the public blog and import the 11 published French articles from the legacy ULTRAPOP database.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE blog_post (
                id INT AUTO_INCREMENT NOT NULL,
                legacy_id INT DEFAULT NULL,
                title VARCHAR(255) NOT NULL,
                slug VARCHAR(255) NOT NULL,
                excerpt LONGTEXT NOT NULL,
                content LONGTEXT NOT NULL,
                cover_image VARCHAR(255) NOT NULL,
                category VARCHAR(100) NOT NULL,
                seo_title VARCHAR(255) DEFAULT NULL,
                seo_description VARCHAR(320) DEFAULT NULL,
                featured TINYINT(1) DEFAULT 0 NOT NULL,
                published TINYINT(1) DEFAULT 1 NOT NULL,
                published_at DATETIME NOT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE INDEX UNIQ_BLOG_POST_LEGACY_ID (legacy_id),
                UNIQUE INDEX UNIQ_BLOG_POST_SLUG (slug),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB',
        );

        foreach ($this->posts() as $post) {
            $this->addSql(
                'INSERT INTO blog_post (
                    legacy_id,
                    title,
                    slug,
                    excerpt,
                    content,
                    cover_image,
                    category,
                    seo_title,
                    seo_description,
                    featured,
                    published,
                    published_at,
                    created_at,
                    updated_at
                ) VALUES (
                    :legacy_id,
                    :title,
                    :slug,
                    :excerpt,
                    :content,
                    :cover_image,
                    :category,
                    :seo_title,
                    :seo_description,
                    :featured,
                    1,
                    :published_at,
                    :created_at,
                    :updated_at
                )',
                [
                    'legacy_id' => $post['legacy_id'],
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
                ],
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE blog_post');
    }

    /**
     * @return list<array{
     *     legacy_id: int,
     *     title: string,
     *     slug: string,
     *     excerpt: string,
     *     content: string,
     *     cover_image: string,
     *     category: string,
     *     seo_title: string,
     *     seo_description: string,
     *     featured: bool,
     *     published_at: string
     * }>
     */
    private function posts(): array
    {
        $path = __DIR__ . '/data/blog_posts.php';
        $posts = require $path;

        if (!is_array($posts)) {
            throw new \RuntimeException(sprintf('Invalid blog import data in %s.', $path));
        }

        return $posts;
    }
}
