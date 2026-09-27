<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Factory\AuthorFakeDataFactory;
use App\Factory\BookAuthorFakeDataFactory;
use App\Factory\BookFakeDataFactory;
use App\Factory\BookReviewFakeDataFactory;
use App\Repository\BookAuthorRepository;
use App\Tests\ApiTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;

class BooksControllerTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->authenticate();
    }

    public function testListReturnsBooksWithTheirAuthors(): void
    {
        $book = BookFakeDataFactory::createOne(['title' => 'Domain-Driven Design', 'isbn' => '9780321125215']);
        $author = AuthorFakeDataFactory::createOne(['name' => 'Eric Evans']);
        BookAuthorFakeDataFactory::createOne(['book_id' => $book, 'author_id' => $author]);

        $this->client->request('GET', '/books');

        self::assertResponseIsSuccessful();
        $data = $this->items();
        self::assertCount(1, $data);
        self::assertSame($book->getId(), $data[0]['id']);
        self::assertSame('Domain-Driven Design', $data[0]['title']);
        self::assertSame('9780321125215', $data[0]['isbn']);
        self::assertSame([['id' => $author->getId(), 'name' => 'Eric Evans']], $data[0]['authors']);
    }

    public function testListIsPaginated(): void
    {
        BookFakeDataFactory::createMany(5);

        $this->client->request('GET', '/books?page=2&size=2');
        self::assertCount(2, $this->items());
        self::assertSame(['total' => 5, 'page' => 2, 'size' => 2], array_diff_key($this->responseData(), ['items' => true]));

        $this->client->request('GET', '/books?page=3&size=2');
        self::assertCount(1, $this->items());
    }

    public function testListReportsTheDefaultPageAndSize(): void
    {
        $this->client->request('GET', '/books');

        self::assertSame(['items' => [], 'total' => 0, 'page' => 1, 'size' => 20], $this->responseData());
    }

    public function testBooksShowTheirAverageRatingAndReviewCount(): void
    {
        $reviewed = BookFakeDataFactory::createOne(['title' => 'A']);
        BookFakeDataFactory::createOne(['title' => 'B']);
        foreach ([5, 4, 4] as $rating) {
            BookReviewFakeDataFactory::createOne(['book_id' => $reviewed, 'rating' => $rating]);
        }

        $this->client->request('GET', '/books?orderBy=title');

        self::assertSame([4.33, null], array_column($this->items(), 'average_rating'));
        self::assertSame([3, 0], array_column($this->items(), 'review_count'));

        $this->client->request('GET', '/books/'.$reviewed->getId());
        self::assertSame(4.33, $this->responseData()['average_rating']);
        self::assertSame(3, $this->responseData()['review_count']);
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function filters(): iterable
    {
        yield 'part of the title, any case' => ['title=DOMAIN', ['Domain-Driven Design']];
        yield 'title wildcards are literal' => ['title=%25', []];
        yield 'whole genre, any case' => ['genre=software', ['Domain-Driven Design', 'Refactoring']];
        yield 'part of the genre does not match' => ['genre=soft', []];
        yield 'part of an author name' => ['author=fowl', ['Refactoring']];
        yield 'minimum average rating' => ['minRating=4.5', ['Refactoring']];
        yield 'minimum average rating, whole number' => ['minRating=4', ['Domain-Driven Design', 'Refactoring']];
        yield 'minimum above the scale' => ['minRating=5.5', []];
        yield 'filters combine' => ['genre=Software&author=e', ['Domain-Driven Design', 'Refactoring']];
        yield 'filters combine to nothing' => ['genre=Fantasy&minRating=1', []];
    }

    /**
     * @param list<string> $titles
     */
    #[DataProvider('filters')]
    public function testListCanBeFiltered(string $query, array $titles): void
    {
        $ddd = BookFakeDataFactory::createOne(['title' => 'Domain-Driven Design', 'genre' => 'Software']);
        $refactoring = BookFakeDataFactory::createOne(['title' => 'Refactoring', 'genre' => 'Software']);
        BookFakeDataFactory::createOne(['title' => 'The Hobbit', 'genre' => 'Fantasy']);
        BookAuthorFakeDataFactory::createOne(['book_id' => $ddd, 'author_id' => AuthorFakeDataFactory::createOne(['name' => 'Eric Evans'])]);
        BookAuthorFakeDataFactory::createOne(['book_id' => $refactoring, 'author_id' => AuthorFakeDataFactory::createOne(['name' => 'Martin Fowler'])]);
        BookReviewFakeDataFactory::createOne(['book_id' => $ddd, 'rating' => 4]);
        BookReviewFakeDataFactory::createOne(['book_id' => $refactoring, 'rating' => 5]);

        $this->client->request('GET', '/books?orderBy=title&'.$query);

        self::assertResponseIsSuccessful();
        self::assertSame($titles, array_column($this->items(), 'title'));
        self::assertSame(count($titles), $this->responseData()['total']);
    }

    public function testMalformedMinRatingIsBadRequest(): void
    {
        $this->client->request('GET', '/books?minRating=high');

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testListCanBeSortedByAnAllowedColumn(): void
    {
        foreach (['Refactoring', 'Clean Code', 'Patterns of Enterprise Application Architecture'] as $title) {
            BookFakeDataFactory::createOne(['title' => $title]);
        }

        $this->client->request('GET', '/books?orderBy=title');

        self::assertSame(
            ['Clean Code', 'Patterns of Enterprise Application Architecture', 'Refactoring'],
            array_column($this->items(), 'title'),
        );
    }

    public function testListIgnoresAnUnknownSortColumn(): void
    {
        BookFakeDataFactory::createMany(2);

        $this->client->request('GET', '/books?orderBy=id;DROP TABLE book');

        self::assertResponseIsSuccessful();
        self::assertCount(2, $this->items());
    }

    public function testMalformedPaginationIsBadRequest(): void
    {
        $this->client->request('GET', '/books?page=abc');

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testGetReturnsASingleBook(): void
    {
        $book = BookFakeDataFactory::createOne(['title' => 'Refactoring', 'price' => '39.99']);

        $this->client->request('GET', '/books/'.$book->getId());

        self::assertResponseIsSuccessful();
        $data = $this->responseData();
        self::assertSame('Refactoring', $data['title']);
        self::assertSame(39.99, $data['price']);
        self::assertSame([], $data['authors']);
    }

    public function testGetUnknownBookIsNotFound(): void
    {
        $this->client->request('GET', '/books/999999');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        self::assertArrayHasKey('error', $this->responseData());
    }

    public function testCreateStoresTheBookAndItsAuthors(): void
    {
        $this->requestJson('POST', '/books', $this->bookPayload());

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $data = $this->responseData();
        self::assertIsInt($data['id']);
        self::assertResponseHeaderSame('Location', 'http://localhost/books/'.$data['id']);
        self::assertSame('Clean Architecture', $data['title']);
        self::assertSame(29.99, $data['price']);
        self::assertSame(['Robert C. Martin', 'Second Author'], array_column($data['authors'], 'name'));

        BookFakeDataFactory::assert()->count(1);
        AuthorFakeDataFactory::assert()->count(2);
        BookAuthorFakeDataFactory::assert()->count(2);
    }

    public function testCreateWithAnExistingIsbnIsAConflict(): void
    {
        BookFakeDataFactory::createOne(['isbn' => '9780134494166', 'title' => 'Old title']);

        $this->requestJson('POST', '/books', $this->bookPayload());

        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        self::assertSame(['error' => 'A book with this ISBN already exists'], $this->responseData());
        BookFakeDataFactory::assert()->count(1);
        BookFakeDataFactory::assert()->exists(['isbn' => '9780134494166', 'title' => 'Old title']);
        AuthorFakeDataFactory::assert()->empty();
    }

    public function testCreateRejectsAnInvalidPayload(): void
    {
        $this->requestJson('POST', '/books', $this->bookPayload(['isbn' => '123']));

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertStringContainsString('isbn', $this->responseData()['error']);
        BookFakeDataFactory::assert()->empty();
    }

    public function testCreateWithoutAuthorsStoresTheBook(): void
    {
        $payload = $this->bookPayload();
        unset($payload['authors']);

        $this->requestJson('POST', '/books', $payload);

        self::assertResponseIsSuccessful();
        self::assertSame([], $this->responseData()['authors']);
        BookFakeDataFactory::assert()->count(1);
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function invalidAuthors(): iterable
    {
        yield 'author without a name' => [['info' => 'No name']];
        yield 'author with a blank name' => [['name' => '', 'info' => null]];
        yield 'author that is a string' => ['Robert C. Martin'];
        yield 'author that is a number' => [42];
    }

    #[DataProvider('invalidAuthors')]
    public function testCreateRejectsAnInvalidAuthor(mixed $author): void
    {
        $this->requestJson('POST', '/books', $this->bookPayload([
            'authors' => [['name' => 'Robert C. Martin', 'info' => null], $author],
        ]));

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertStringContainsString('authors', $this->responseData()['error']);
        BookFakeDataFactory::assert()->empty();
        AuthorFakeDataFactory::assert()->empty();
    }

    public function testCreateFailingPartWayStoresNothing(): void
    {
        $bookAuthorRepository = $this->createMock(BookAuthorRepository::class);
        $bookAuthorRepository->method('createBookAuthor')->willThrowException(new \RuntimeException('Database failure'));
        static::getContainer()->set(BookAuthorRepository::class, $bookAuthorRepository);

        $this->requestJson('POST', '/books', $this->bookPayload());

        self::assertResponseStatusCodeSame(Response::HTTP_INTERNAL_SERVER_ERROR);
        BookFakeDataFactory::assert()->empty();
        AuthorFakeDataFactory::assert()->empty();
    }

    public function testCreateRejectsANonNumericPrice(): void
    {
        $this->requestJson('POST', '/books', $this->bookPayload(['price' => 'abc']));

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertStringContainsString('Price must be a decimal number', $this->responseData()['error']);
        BookFakeDataFactory::assert()->empty();
    }

    public function testGetReviewsReturnsOnlyThatBooksReviews(): void
    {
        $book = BookFakeDataFactory::createOne();
        BookReviewFakeDataFactory::createMany(2, ['book_id' => $book]);
        BookReviewFakeDataFactory::createOne(['book_id' => BookFakeDataFactory::createOne()]);

        $this->client->request('GET', '/books/'.$book->getId().'/reviews');

        self::assertResponseIsSuccessful();
        $data = $this->responseData();
        self::assertCount(2, $data);
        self::assertSame([$book->getId(), $book->getId()], array_column($data, 'book_id'));
    }

    public function testGetReviewsOfABookWithoutReviewsIsAnEmptyList(): void
    {
        $book = BookFakeDataFactory::createOne();

        $this->client->request('GET', '/books/'.$book->getId().'/reviews');

        self::assertResponseIsSuccessful();
        self::assertSame([], $this->responseData());
    }

    public function testCreateReviewAddsItToTheBook(): void
    {
        $book = BookFakeDataFactory::createOne(['title' => 'Refactoring']);

        $this->requestJson('POST', '/books/'.$book->getId().'/reviews', [
            'name' => ' Jane ',
            'content' => 'Worth reading twice.',
            'rating' => '4',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $data = $this->responseData();
        self::assertSame($book->getId(), $data['book_id']);
        self::assertSame('Refactoring', $data['book_name']);
        self::assertSame('Jane', $data['reviewer']);
        self::assertSame(4, $data['rating']);
        self::assertNotEmpty($data['submit_date']);
        BookReviewFakeDataFactory::assert()->count(1);
    }

    public function testCreateReviewRejectsTooShortContent(): void
    {
        $book = BookFakeDataFactory::createOne();

        $this->requestJson('POST', '/books/'.$book->getId().'/reviews', ['name' => 'Jane', 'content' => 'ok', 'rating' => '4']);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        BookReviewFakeDataFactory::assert()->empty();
    }

    public function testCreateReviewRejectsARatingOutsideTheScale(): void
    {
        $book = BookFakeDataFactory::createOne();

        $this->requestJson('POST', '/books/'.$book->getId().'/reviews', ['name' => 'Jane', 'content' => 'Worth reading.', 'rating' => 'abcde']);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertStringContainsString('Rating must be a whole number from 0 to 5.', $this->responseData()['error']);
        BookReviewFakeDataFactory::assert()->empty();
    }

    public function testCreateReviewForAnUnknownBookIsNotFound(): void
    {
        $this->requestJson('POST', '/books/999999/reviews', ['name' => 'Jane', 'content' => 'Worth reading.', 'rating' => '4']);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testReplaceSetsEveryFieldAndTheAuthors(): void
    {
        $this->authenticate(['ROLE_ADMIN']);
        $book = BookFakeDataFactory::createOne(['isbn' => '9780134494166']);
        $kept = AuthorFakeDataFactory::createOne(['name' => 'Robert C. Martin']);
        BookAuthorFakeDataFactory::createOne(['book_id' => $book, 'author_id' => $kept]);
        BookAuthorFakeDataFactory::createOne(['book_id' => $book, 'author_id' => AuthorFakeDataFactory::createOne(['name' => 'Dropped'])]);

        $this->requestJson('PUT', '/books/'.$book->getId(), $this->bookPayload([
            'title' => 'Clean Architecture, 2nd ed.',
            'authors' => [['name' => 'Robert C. Martin', 'info' => null], ['name' => 'New Author', 'info' => null]],
        ]));

        self::assertResponseIsSuccessful();
        $data = $this->responseData();
        self::assertSame($book->getId(), $data['id']);
        self::assertSame('Clean Architecture, 2nd ed.', $data['title']);
        self::assertSame(29.99, $data['price']);
        self::assertSame(['Robert C. Martin', 'New Author'], array_column($data['authors'], 'name'));
        self::assertSame($kept->getId(), $data['authors'][0]['id']);
        BookAuthorFakeDataFactory::assert()->count(2);
        AuthorFakeDataFactory::assert()->exists(['name' => 'Dropped']);
    }

    public function testReplaceWithoutAuthorsRemovesThem(): void
    {
        $this->authenticate(['ROLE_ADMIN']);
        $book = BookFakeDataFactory::createOne();
        BookAuthorFakeDataFactory::createOne(['book_id' => $book, 'author_id' => AuthorFakeDataFactory::createOne()]);
        $payload = $this->bookPayload();
        unset($payload['authors']);

        $this->requestJson('PUT', '/books/'.$book->getId(), $payload);

        self::assertResponseIsSuccessful();
        self::assertSame([], $this->responseData()['authors']);
        BookAuthorFakeDataFactory::assert()->empty();
    }

    public function testReplaceMayKeepTheBooksOwnIsbn(): void
    {
        $this->authenticate(['ROLE_ADMIN']);
        $book = BookFakeDataFactory::createOne(['isbn' => '9780134494166']);

        $this->requestJson('PUT', '/books/'.$book->getId(), $this->bookPayload());

        self::assertResponseIsSuccessful();
    }

    public function testReplaceWithAnotherBooksIsbnIsAConflict(): void
    {
        $this->authenticate(['ROLE_ADMIN']);
        BookFakeDataFactory::createOne(['isbn' => '9780134494166']);
        $book = BookFakeDataFactory::createOne(['isbn' => '9780321125215', 'title' => 'Domain-Driven Design']);

        $this->requestJson('PUT', '/books/'.$book->getId(), $this->bookPayload());

        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        BookFakeDataFactory::assert()->exists(['isbn' => '9780321125215', 'title' => 'Domain-Driven Design']);
    }

    public function testReplaceRejectsAnIncompletePayload(): void
    {
        $this->authenticate(['ROLE_ADMIN']);
        $book = BookFakeDataFactory::createOne();

        $this->requestJson('PUT', '/books/'.$book->getId(), ['title' => 'Only a title']);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testPatchChangesOnlyTheGivenFields(): void
    {
        $this->authenticate(['ROLE_ADMIN']);
        $book = BookFakeDataFactory::createOne(['title' => 'Old title', 'genre' => 'Software', 'price' => '10.00']);
        BookAuthorFakeDataFactory::createOne(['book_id' => $book, 'author_id' => AuthorFakeDataFactory::createOne(['name' => 'Kept'])]);

        $this->requestJson('PATCH', '/books/'.$book->getId(), ['price' => '12.50']);

        self::assertResponseIsSuccessful();
        $data = $this->responseData();
        self::assertSame(12.5, $data['price']);
        self::assertSame('Old title', $data['title']);
        self::assertSame('Software', $data['genre']);
        self::assertSame(['Kept'], array_column($data['authors'], 'name'));
    }

    public function testPatchCanClearThePublishDate(): void
    {
        $this->authenticate(['ROLE_ADMIN']);
        $book = BookFakeDataFactory::createOne(['publish_date' => new \DateTimeImmutable('2001-02-03')]);

        $this->client->request('GET', '/books/'.$book->getId());
        self::assertSame('2001-02-03', $this->responseData()['publish_date']);

        $this->requestJson('PATCH', '/books/'.$book->getId(), ['publish_date' => '']);

        self::assertResponseIsSuccessful();
        self::assertNull($this->responseData()['publish_date']);
    }

    public function testCreateRejectsAnImpossiblePublishDate(): void
    {
        $this->requestJson('POST', '/books', $this->bookPayload(['publish_date' => '2017-02-30']));

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertStringContainsString('YYYY-MM-DD', $this->responseData()['error']);
    }

    public function testPatchWithAuthorsReplacesThem(): void
    {
        $this->authenticate(['ROLE_ADMIN']);
        $book = BookFakeDataFactory::createOne();
        BookAuthorFakeDataFactory::createOne(['book_id' => $book, 'author_id' => AuthorFakeDataFactory::createOne(['name' => 'Old'])]);

        $this->requestJson('PATCH', '/books/'.$book->getId(), ['authors' => [['name' => 'New', 'info' => null]]]);

        self::assertSame(['New'], array_column($this->responseData()['authors'], 'name'));
    }

    public function testPatchRejectsAnInvalidField(): void
    {
        $this->authenticate(['ROLE_ADMIN']);
        $book = BookFakeDataFactory::createOne(['title' => 'Old title']);

        $this->requestJson('PATCH', '/books/'.$book->getId(), ['price' => 'free', 'title' => '']);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        BookFakeDataFactory::assert()->exists(['title' => 'Old title']);
    }

    public function testUpdateOfAnUnknownBookIsNotFound(): void
    {
        $this->authenticate(['ROLE_ADMIN']);

        $this->requestJson('PATCH', '/books/999999', ['title' => 'Anything']);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function adminOnlyMethods(): iterable
    {
        yield 'replace' => ['PUT'];
        yield 'patch' => ['PATCH'];
        yield 'delete' => ['DELETE'];
    }

    #[DataProvider('adminOnlyMethods')]
    public function testChangingABookRequiresAnAdmin(string $method): void
    {
        $book = BookFakeDataFactory::createOne(['title' => 'Untouched']);

        $this->requestJson($method, '/books/'.$book->getId(), $this->bookPayload());

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        self::assertSame(['error' => 'Forbidden'], $this->responseData());
        BookFakeDataFactory::assert()->exists(['title' => 'Untouched']);
    }

    public function testDeleteRemovesTheBookAndItsReviews(): void
    {
        $this->authenticate(['ROLE_ADMIN']);
        $book = BookFakeDataFactory::createOne();
        BookReviewFakeDataFactory::createOne(['book_id' => $book]);
        $id = $book->getId();

        $this->client->request('DELETE', '/books/'.$id);

        self::assertResponseIsSuccessful();
        self::assertSame(['result' => true], $this->responseData());
        BookFakeDataFactory::assert()->notExists(['id' => $id]);
        BookReviewFakeDataFactory::assert()->empty();
    }

    public function testDeleteRemovesTheAuthorLinksButKeepsTheAuthors(): void
    {
        $this->authenticate(['ROLE_ADMIN']);
        $book = BookFakeDataFactory::createOne();
        $otherBook = BookFakeDataFactory::createOne();
        $author = AuthorFakeDataFactory::createOne();
        BookAuthorFakeDataFactory::createOne(['book_id' => $book, 'author_id' => $author]);
        BookAuthorFakeDataFactory::createOne(['book_id' => $otherBook, 'author_id' => $author]);

        $this->client->request('DELETE', '/books/'.$book->getId());

        self::assertResponseIsSuccessful();
        BookFakeDataFactory::assert()->count(1);
        BookAuthorFakeDataFactory::assert()->count(1);
        AuthorFakeDataFactory::assert()->count(1);

        $this->client->request('GET', '/books/'.$otherBook->getId());
        self::assertSame([$author->getName()], array_column($this->responseData()['authors'], 'name'));
    }

    public function testDeleteUnknownBookIsNotFound(): void
    {
        $this->authenticate(['ROLE_ADMIN']);
        $this->client->request('DELETE', '/books/999999');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function bookPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => ' Clean Architecture ',
            'isbn' => '9780134494166',
            'description' => 'A craftsman\'s guide to software structure and design',
            'price' => '29.987',
            'genre' => 'Software',
            'publish_date' => '2017-09-10',
            'authors' => [
                ['name' => 'Robert C. Martin', 'info' => 'Uncle Bob'],
                ['name' => 'Second Author', 'info' => null],
            ],
        ], $overrides);
    }
}
