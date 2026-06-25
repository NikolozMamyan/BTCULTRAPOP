<?php

namespace App\Repository;

use App\Entity\BlogPost;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BlogPost>
 */
final class BlogPostRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BlogPost::class);
    }

    /**
     * @return list<BlogPost>
     */
    public function findPublished(): array
    {
        return $this->publishedQuery()
            ->orderBy('post.featured', 'DESC')
            ->addOrderBy('post.publishedAt', 'DESC')
            ->addOrderBy('post.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findPublishedBySlug(string $slug): ?BlogPost
    {
        return $this->publishedQuery()
            ->andWhere('post.slug = :slug')
            ->setParameter('slug', mb_strtolower(trim($slug)))
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return list<BlogPost>
     */
    public function findRelated(BlogPost $post, int $limit = 3): array
    {
        return $this->publishedQuery()
            ->andWhere('post != :post')
            ->setParameter('post', $post)
            ->addSelect('CASE WHEN post.category = :category THEN 0 ELSE 1 END AS HIDDEN category_match')
            ->setParameter('category', $post->getCategory())
            ->orderBy('category_match', 'ASC')
            ->addOrderBy('post.publishedAt', 'DESC')
            ->setMaxResults(max(1, min(6, $limit)))
            ->getQuery()
            ->getResult();
    }

    private function publishedQuery(): \Doctrine\ORM\QueryBuilder
    {
        return $this->createQueryBuilder('post')
            ->andWhere('post.published = true')
            ->andWhere('post.publishedAt <= :now')
            ->setParameter('now', new \DateTimeImmutable());
    }
}
