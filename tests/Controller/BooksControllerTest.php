<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Factory\AuthorFakeDataFactory;
use App\Factory\BookAuthorFakeDataFactory;
use App\Factory\BookFakeDataFactory;
use App\Factory\BookReviewFakeDataFactory;
use App\Tests\ApiTestCase;
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
        $data = $this->responseData();
        self::assertCount(1, $data);
        self::assertSame($book->getId(), $data[0]['id']);
        self::assertSame('Domain-Driven Design', $data[0]['title']);
        self::assertSame('9780321125215', $data[0]['isbn']);
        self::assertSame('Eric Evans', $data[0]['authors'][0]['name']);
    }

    public function testListIsPaginated(): void
    {
        BookFakeDataFactory::createMany(5);

        $this->client->request('GET', '/books?page=2&size=2');
        self::assertCount(2, $this->responseData());

        $this->client->request('GET', '/books?page=3&size=2');
        self::assertCount(1, $this->responseData());
    }

    public function testListCanBeSortedByAnAllowedColumn(): void
    {
        foreach (['Refactoring', 'Clean Code', 'Patterns of Enterprise Application Architecture'] as $title) {
            BookFakeDataFactory::createOne(['title' => $title]);
        }

        $this->client->request('GET', '/books?orderBy=title');

        self::assertSame(
            ['Clean Code', 'Patterns of Enterprise Application Architecture', 'Refactoring'],
            array_column($this->responseData(), 'title'),
        );
    }

    public function testListIgnoresAnUnknownSortColumn(): void
    {
        BookFakeDataFactory::createMany(2);

        $this->client->request('GET', '/books?orderBy=id;DROP TABLE book');

        self::assertResponseIsSuccessful();
        self::assertCount(2, $this->responseData());
    }

    public function testMalformedPaginationIsBadRequest(): void
    {
        $this->client->request('GET', '/books?page=abc');

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testGetReturnsASingleBook(): void
    {
        $book = BookFakeDataFactory::createOne(['title' => 'Refactoring', 'price' => 39.99]);

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

        self::assertResponseIsSuccessful();
        $data = $this->responseData();
        self::assertIsInt($data['id']);
        self::assertSame('Clean Architecture', $data['title']);
        self::assertSame(29.99, $data['price']);
        self::assertSame(['Robert C. Martin', 'Second Author'], array_column($data['authors'], 'name'));

        BookFakeDataFactory::assert()->count(1);
        AuthorFakeDataFactory::assert()->count(2);
        BookAuthorFakeDataFactory::assert()->count(2);
    }

    public function testCreateWithAnExistingIsbnUpdatesThatBook(): void
    {
        $book = BookFakeDataFactory::createOne(['isbn' => '9780134494166', 'title' => 'Old title']);

        $this->requestJson('POST', '/books', $this->bookPayload(['authors' => []]));

        self::assertResponseIsSuccessful();
        self::assertSame($book->getId(), $this->responseData()['id']);
        BookFakeDataFactory::assert()->count(1);
        BookFakeDataFactory::assert()->exists(['isbn' => '9780134494166', 'title' => 'Clean Architecture']);
    }

    public function testCreateRejectsAnInvalidPayload(): void
    {
        $this->requestJson('POST', '/books', $this->bookPayload(['isbn' => '123']));

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertStringContainsString('isbn', $this->responseData()['error']);
        BookFakeDataFactory::assert()->empty();
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

    public function testDeleteRemovesTheBookAndItsReviews(): void
    {
        $book = BookFakeDataFactory::createOne();
        BookReviewFakeDataFactory::createOne(['book_id' => $book]);
        $id = $book->getId();

        $this->client->request('DELETE', '/book/delete/'.$id);

        self::assertResponseIsSuccessful();
        self::assertSame(['result' => true], $this->responseData());
        BookFakeDataFactory::assert()->notExists(['id' => $id]);
        BookReviewFakeDataFactory::assert()->empty();
    }

    public function testDeleteRemovesTheAuthorLinksButKeepsTheAuthors(): void
    {
        $book = BookFakeDataFactory::createOne();
        $otherBook = BookFakeDataFactory::createOne();
        $author = AuthorFakeDataFactory::createOne();
        BookAuthorFakeDataFactory::createOne(['book_id' => $book, 'author_id' => $author]);
        BookAuthorFakeDataFactory::createOne(['book_id' => $otherBook, 'author_id' => $author]);

        $this->client->request('DELETE', '/book/delete/'.$book->getId());

        self::assertResponseIsSuccessful();
        BookFakeDataFactory::assert()->count(1);
        BookAuthorFakeDataFactory::assert()->count(1);
        AuthorFakeDataFactory::assert()->count(1);

        $this->client->request('GET', '/books/'.$otherBook->getId());
        self::assertSame([$author->getName()], array_column($this->responseData()['authors'], 'name'));
    }

    public function testDeleteUnknownBookIsNotFound(): void
    {
        $this->client->request('DELETE', '/book/delete/999999');

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
