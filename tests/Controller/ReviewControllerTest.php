<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\User;
use App\Factory\BookFakeDataFactory;
use App\Factory\BookReviewFakeDataFactory;
use App\Factory\UserFakeDataFactory;
use App\Tests\ApiTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;

class ReviewControllerTest extends ApiTestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = $this->authenticate();
    }

    public function testListReturnsReviewsOfAllBooks(): void
    {
        $book = BookFakeDataFactory::createOne(['title' => 'Refactoring']);
        BookReviewFakeDataFactory::createOne(['book_id' => $book, 'name' => 'Jane', 'content' => 'Great', 'rating' => 5]);
        BookReviewFakeDataFactory::createOne(['book_id' => BookFakeDataFactory::createOne()]);

        $this->client->request('GET', '/reviews?orderBy=name');

        self::assertResponseIsSuccessful();
        $data = $this->items();
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
        self::assertCount(1, $this->items());
        self::assertSame(3, $this->responseData()['total']);
    }

    public function testListCanBeSortedByRating(): void
    {
        $book = BookFakeDataFactory::createOne();
        foreach ([3, 1, 5] as $rating) {
            BookReviewFakeDataFactory::createOne(['book_id' => $book, 'rating' => $rating]);
        }

        $this->client->request('GET', '/reviews?orderBy=rating');

        self::assertSame([1, 3, 5], array_column($this->items(), 'rating'));
    }

    public function testListCanBeFilteredByRating(): void
    {
        $book = BookFakeDataFactory::createOne();
        foreach ([3, 5, 5] as $rating) {
            BookReviewFakeDataFactory::createOne(['book_id' => $book, 'rating' => $rating]);
        }

        $this->client->request('GET', '/reviews?rating=5');

        self::assertSame([5, 5], array_column($this->items(), 'rating'));
        self::assertSame(2, $this->responseData()['total']);
    }

    public function testGetReturnsASingleReview(): void
    {
        $review = BookReviewFakeDataFactory::createOne(['book_id' => BookFakeDataFactory::createOne(), 'content' => 'Worth it', 'rating' => 4]);

        $this->client->request('GET', '/reviews/'.$review->getId());

        self::assertResponseIsSuccessful();
        self::assertSame($review->getId(), $this->responseData()['review_id']);
        self::assertSame('Worth it', $this->responseData()['content']);
    }

    public function testGetUnknownReviewIsNotFound(): void
    {
        $this->client->request('GET', '/reviews/999999');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testMalformedPageSizeIsBadRequest(): void
    {
        $this->client->request('GET', '/reviews?size=many');

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testDeleteRemovesTheReview(): void
    {
        $review = BookReviewFakeDataFactory::createOne(['book_id' => BookFakeDataFactory::createOne(), 'user' => $this->user]);

        $this->client->request('DELETE', '/reviews/'.$review->getId());

        self::assertResponseIsSuccessful();
        self::assertSame(['result' => true], $this->responseData());
        BookReviewFakeDataFactory::assert()->empty();
        BookFakeDataFactory::assert()->count(1);
    }

    public function testDeleteUnknownReviewIsNotFound(): void
    {
        $this->client->request('DELETE', '/reviews/999999');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testCreatedReviewBelongsToTheUser(): void
    {
        $book = BookFakeDataFactory::createOne();

        $this->requestJson('POST', '/books/'.$book->getId().'/reviews', ['name' => 'Me', 'content' => 'Solid read', 'rating' => '4']);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        self::assertSame($this->user->getId(), $this->responseData()['user_id']);
    }

    public function testOwnerCanReplaceTheReview(): void
    {
        $review = $this->ownReview();

        $this->requestJson('PUT', '/reviews/'.$review->getId(), ['name' => 'Jane', 'content' => 'Changed my mind', 'rating' => '2']);

        self::assertResponseIsSuccessful();
        $data = $this->responseData();
        self::assertSame(['Jane', 'Changed my mind', 2], [$data['reviewer'], $data['content'], $data['rating']]);
    }

    public function testOwnerCanPatchTheReview(): void
    {
        $review = $this->ownReview();

        $this->requestJson('PATCH', '/reviews/'.$review->getId(), ['rating' => '1']);

        self::assertResponseIsSuccessful();
        self::assertSame(1, $this->responseData()['rating']);
        self::assertSame('Original', $this->responseData()['content']);
    }

    public function testPatchRejectsAnInvalidRating(): void
    {
        $review = $this->ownReview();

        $this->requestJson('PATCH', '/reviews/'.$review->getId(), ['rating' => '9']);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testUpdateOfAnUnknownReviewIsNotFound(): void
    {
        $this->requestJson('PATCH', '/reviews/999999', ['rating' => '1']);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    /**
     * @return iterable<string, array{string, array<string, string>}>
     */
    public static function changes(): iterable
    {
        yield 'replace' => ['PUT', ['name' => 'X', 'content' => 'Hijacked', 'rating' => '0']];
        yield 'patch' => ['PATCH', ['content' => 'Hijacked']];
        yield 'delete' => ['DELETE', []];
    }

    /**
     * @param array<string, string> $payload
     */
    #[DataProvider('changes')]
    public function testOtherUsersCannotChangeTheReview(string $method, array $payload): void
    {
        $review = BookReviewFakeDataFactory::createOne([
            'book_id' => BookFakeDataFactory::createOne(),
            'user' => UserFakeDataFactory::createOne(),
            'content' => 'Original',
        ]);

        $this->requestJson($method, '/reviews/'.$review->getId(), $payload);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        BookReviewFakeDataFactory::assert()->exists(['id' => $review->getId(), 'content' => 'Original']);
    }

    /**
     * @param array<string, string> $payload
     */
    #[DataProvider('changes')]
    public function testReviewsWithoutAnOwnerAreAdminOnly(string $method, array $payload): void
    {
        $review = BookReviewFakeDataFactory::createOne(['book_id' => BookFakeDataFactory::createOne(), 'content' => 'Original']);

        $this->requestJson($method, '/reviews/'.$review->getId(), $payload);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    /**
     * @param array<string, string> $payload
     */
    #[DataProvider('changes')]
    public function testAdminsCanChangeAnyReview(string $method, array $payload): void
    {
        $this->authenticate(['ROLE_ADMIN']);
        $review = BookReviewFakeDataFactory::createOne([
            'book_id' => BookFakeDataFactory::createOne(),
            'user' => UserFakeDataFactory::createOne(),
        ]);

        $this->requestJson($method, '/reviews/'.$review->getId(), $payload);

        self::assertResponseIsSuccessful();
    }

    private function ownReview(): object
    {
        return BookReviewFakeDataFactory::createOne([
            'book_id' => BookFakeDataFactory::createOne(),
            'user' => $this->user,
            'content' => 'Original',
            'rating' => 5,
        ]);
    }
}
