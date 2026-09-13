<?php

declare(strict_types=1);

namespace App\Tests\Output;

use App\Entity\Book;
use App\Entity\Review;
use App\Output\ReviewData;
use PHPUnit\Framework\TestCase;

class ReviewDataTest extends TestCase
{
    public function testOutputContainsTheReviewAndItsBook(): void
    {
        $submitted = new \DateTimeImmutable('2025-05-20 10:00:00');
        $review = (new Review())
            ->setId(9)
            ->setBook((new Book())->setId(3)->setTitle('Refactoring'))
            ->setName('Jane')
            ->setContent('Worth reading twice.')
            ->setRating(4)
            ->setSubmitDate($submitted);

        self::assertSame([
            'review_id' => 9,
            'book_id' => 3,
            'book_name' => 'Refactoring',
            'reviewer' => 'Jane',
            'content' => 'Worth reading twice.',
            'rating' => 4,
            'submit_date' => $submitted,
        ], (new ReviewData())->getOutput($review));
    }

    public function testReviewWithoutABookHasNullBookFields(): void
    {
        $output = (new ReviewData())->getOutput((new Review())->setContent('Orphan'));

        self::assertNull($output['book_id']);
        self::assertNull($output['book_name']);
    }
}
