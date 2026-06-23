<?php

namespace App\Service;

use App\Entity\Product;
use App\Entity\ProductReview;
use App\Repository\ProductReviewRepository;

final readonly class StorefrontProductReviews
{
    public function __construct(private ProductReviewRepository $reviews)
    {
    }

    /**
     * @return array{
     *     average: float,
     *     count: int,
     *     distribution: array<int, array{count: int, percentage: int}>,
     *     items: list<array{
     *         author: string,
     *         title: ?string,
     *         content: string,
     *         rating: int,
     *         editorial: bool,
     *         created_at: \DateTimeImmutable
     *     }>
     * }
     */
    public function forProduct(Product $product): array
    {
        $reviews = $this->reviews->findPublishedForProduct($product);
        $count = count($reviews);
        $ratingTotal = 0;
        $distribution = array_fill(1, 5, 0);

        foreach ($reviews as $review) {
            $rating = $review->getRating();
            $ratingTotal += $rating;
            ++$distribution[$rating];
        }

        $average = $count > 0 ? round($ratingTotal / $count, 1) : 0.0;

        return [
            'average' => $average,
            'count' => $count,
            'distribution' => array_map(
                static fn (int $ratingCount): array => [
                    'count' => $ratingCount,
                    'percentage' => $count > 0 ? (int) round(($ratingCount / $count) * 100) : 0,
                ],
                $distribution,
            ),
            'items' => array_map(
                static fn (ProductReview $review): array => [
                    'author' => $review->getAuthorName(),
                    'title' => $review->getTitle(),
                    'content' => $review->getContent(),
                    'rating' => $review->getRating(),
                    'editorial' => $review->isEditorial(),
                    'created_at' => $review->getCreatedAt(),
                ],
                $reviews,
            ),
        ];
    }
}
