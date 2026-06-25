<?php

namespace App\Service;

use App\Entity\BlogPost;

final readonly class BlogPostPresenter
{
    public function __construct(private AssetUrlResolver $assetUrlResolver)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function present(BlogPost $post, bool $withContent = false): array
    {
        $presented = [
            'id' => $post->getId(),
            'title' => $post->getTitle(),
            'slug' => $post->getSlug(),
            'excerpt' => $post->getExcerpt(),
            'cover_image' => $this->assetUrlResolver->resolve($post->getCoverImage()),
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
