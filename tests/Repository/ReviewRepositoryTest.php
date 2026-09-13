<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Dto\CreateReview;
use App\Factory\BookFakeDataFactory;
use App\Factory\BookReviewFakeDataFactory;
use App\Repository\ReviewRepository;

class ReviewRepositoryTest extends RepositoryTestCase
{
    public function testCreateStoresATrimmedReviewForTheBook(): void
    {
        $book = BookFakeDataFactory::createOne()->_real();
        $before = new \DateTimeImmutable('-1 second');

        $review = $this->service(ReviewRepository::class)
            ->create($book, new CreateReview(name: ' Jane ', content: ' Worth reading. ', rating: ' 4 '));

        self::assertNotNull($review->getId());
        self::assertSame($book, $review->getBook());
        self::assertSame('Jane', $review->getName());
        self::assertSame('Worth reading.', $review->getContent());
        self::assertSame(4, $review->getRating());
        self::assertGreaterThanOrEqual($before, $review->getSubmitDate());
    }

    public function testFindByBookIdReturnsOnlyThatBooksReviews(): void
    {
        $book = BookFakeDataFactory::createOne();
        BookReviewFakeDataFactory::createMany(2, ['book_id' => $book]);
        BookReviewFakeDataFactory::createOne(['book_id' => BookFakeDataFactory::createOne()]);

        $reviews = $this->service(ReviewRepository::class)->findByBookId($book->getId());

        self::assertCount(2, $reviews);
        foreach ($reviews as $review) {
            self::assertSame($book->getId(), $review->getBook()->getId());
        }
    }

    public function testRemoveReviewReportsWhetherAReviewWasDeleted(): void
    {
        $id = BookReviewFakeDataFactory::createOne(['book_id' => BookFakeDataFactory::createOne()])->getId();
        $repository = $this->service(ReviewRepository::class);

        self::assertTrue($repository->removeReview($id));
        self::assertFalse($repository->removeReview($id));
    }
}
