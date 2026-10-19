<?php

namespace App\Service;

use App\Entity\BlogPost;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class BlogPostPresenter
{
    private const PADDED_COVERS = [
        'anime.png', 'bleach.png', 'demon_slayer.png', 'fruits_basket.png',
        'jujustu-kaisen_1.png', 'jujustu-kaisen_2.png', 'naruto_1.png',
        'naruto_2.png', 'one_piece_1.png', 'unknown_anime.png',
    ];

    public function __construct(
        private AssetUrlResolver $assetUrlResolver,
        #[Autowire('%kernel.project_dir%')]
        private string $projectDirectory,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function present(BlogPost $post, bool $withContent = false): array
    {
        $coverPath = $post->getCoverImage();
        $coverFile = $this->projectDirectory . '/assets/' . $coverPath;
        $dimensions = str_starts_with($coverPath, 'img/blog/') && is_file($coverFile) ? getimagesize($coverFile) : false;
        $presented = [
            'id' => $post->getId(),
            'title' => $post->getTitle(),
            'slug' => $post->getSlug(),
            'excerpt' => $post->getExcerpt(),
            'cover_image' => $this->assetUrlResolver->resolve($post->getCoverImage()),
            'cover_has_padding' => str_starts_with($coverPath, 'img/blog/') && in_array(basename($coverPath), self::PADDED_COVERS, true),
            'cover_width' => $dimensions[0] ?? null,
            'cover_height' => $dimensions[1] ?? null,
            'category' => $post->getCategory(),
            'seo_title' => $post->getSeoTitle() ?: $post->getTitle() . ' | Blog ULTRAPOP',
            'seo_description' => $post->getSeoDescription() ?: $post->getExcerpt(),
            'featured' => $post->isFeatured(),
            'published_at' => $post->getPublishedAt(),
            'updated_at' => $post->getUpdatedAt(),
            'reading_time' => $this->readingTime($post->getContent()),
        ];

        if ($withContent) {
            $presented['content'] = preg_replace_callback(
                '/@@BLOG_ASSET:([a-zA-Z0-9._-]+)@@/',
                fn (array $matches): string => (string) $this->assetUrlResolver->resolve('img/blog/' . $matches[1]),
                $post->getContent(),
            );
        }

        return $presented;
    }

    /**
     * @param list<BlogPost> $posts
     *
     * @return list<array<string, mixed>>
     */
    public function presentMany(array $posts): array
    {
        return array_map(
            fn (BlogPost $post): array => $this->present($post),
            $posts,
        );
    }

    private function readingTime(string $content): int
    {
        $words = str_word_count(strip_tags(html_entity_decode($content, \ENT_QUOTES | \ENT_HTML5, 'UTF-8')));

        return max(1, (int) ceil($words / 220));
    }
}
