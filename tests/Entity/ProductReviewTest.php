<?php

namespace App\Tests\Entity;

use App\Entity\Product;
use App\Entity\ProductReview;
use PHPUnit\Framework\TestCase;

final class ProductReviewTest extends TestCase
{
    public function testReviewContentAndEditorialStateAreExposed(): void
    {
        $createdAt = new \DateTimeImmutable('2026-06-20 12:00:00');
        $product = new Product();
        $review = (new ProductReview())
            ->setProduct($product)
            ->setAuthorName('  Sélection ULTRAPOP  ')
            ->setTitle('  Une belle découverte  ')
            ->setContent('  Une association pop et gourmande réussie.  ')
            ->setRating(5)
            ->setEditorial(true)
            ->setPublished(true)
            ->setCreatedAt($createdAt);

        self::assertSame($product, $review->getProduct());
        self::assertSame('Sélection ULTRAPOP', $review->getAuthorName());
        self::assertSame('Une belle découverte', $review->getTitle());
        self::assertSame('Une association pop et gourmande réussie.', $review->getContent());
        self::assertSame(5, $review->getRating());
        self::assertTrue($review->isEditorial());
        self::assertTrue($review->isPublished());
        self::assertSame($createdAt, $review->getCreatedAt());
    }

    public function testBlankOptionalTitleBecomesNull(): void
    {
        $review = (new ProductReview())->setTitle('  ');

        self::assertNull($review->getTitle());
    }
}
