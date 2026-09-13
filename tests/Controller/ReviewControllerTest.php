<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Factory\BookFakeDataFactory;
use App\Factory\BookReviewFakeDataFactory;
use App\Tests\ApiTestCase;
use Symfony\Component\HttpFoundation\Response;

class ReviewControllerTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->authenticate();
    }

    public function testListReturnsReviewsOfAllBooks(): void
    {
        $book = BookFakeDataFactory::createOne(['title' => 'Refactoring']);
        BookReviewFakeDataFactory::createOne(['book_id' => $book, 'name' => 'Jane', 'content' => 'Great', 'rating' => 5]);
        BookReviewFakeDataFactory::createOne(['book_id' => BookFakeDataFactory::createOne()]);

        $this->client->request('GET', '/reviews?orderBy=name');

        self::assertResponseIsSuccessful();
        $data = $this->responseData();
        self::assertCount(2, $data);

        $jane = array_values(array_filter($data, static fn (array $review): bool => 'Jane' === $review['reviewer']))[0];
        self::assertSame($book->getId(), $jane['book_id']);
        self::assertSame('Refactoring', $jane['book_name']);
        self::assertSame('Great', $jane['content']);
        self::assertSame(5, $jane['rating']);
    }

    public function testListIsPaginated(): void
    {
        BookReviewFakeDataFactory::createMany(3, ['book_id' => BookFakeDataFactory::createOne()]);

        $this->client->request('GET', '/reviews?page=2&size=2');

        self::assertResponseIsSuccessful();
        self::assertCount(1, $this->responseData());
    }

    public function testListCanBeSortedByRating(): void
    {
        $book = BookFakeDataFactory::createOne();
        foreach ([3, 1, 5] as $rating) {
            BookReviewFakeDataFactory::createOne(['book_id' => $book, 'rating' => $rating]);
        }

        $this->client->request('GET', '/reviews?orderBy=rating');

        self::assertSame([1, 3, 5], array_column($this->responseData(), 'rating'));
    }

    public function testMalformedPageSizeIsBadRequest(): void
    {
        $this->client->request('GET', '/reviews?size=many');

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testDeleteRemovesTheReview(): void
    {
        $review = BookReviewFakeDataFactory::createOne(['book_id' => BookFakeDataFactory::createOne()]);

        $this->client->request('DELETE', '/review/delete/'.$review->getId());

        self::assertResponseIsSuccessful();
        self::assertSame(['result' => true], $this->responseData());
        BookReviewFakeDataFactory::assert()->empty();
        BookFakeDataFactory::assert()->count(1);
    }

    public function testDeleteUnknownReviewIsNotFound(): void
    {
        $this->client->request('DELETE', '/review/delete/999999');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }
}
