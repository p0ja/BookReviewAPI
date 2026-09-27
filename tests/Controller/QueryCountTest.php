<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Factory\AuthorFakeDataFactory;
use App\Factory\BookAuthorFakeDataFactory;
use App\Factory\BookFakeDataFactory;
use App\Factory\BookReviewFakeDataFactory;
use App\Tests\ApiTestCase;
use Symfony\Bridge\Doctrine\DataCollector\DoctrineDataCollector;

/**
 * Guards against N+1 queries: a list page costs the same number of queries whether it
 * holds one row or many.
 */
class QueryCountTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->authenticate();
        // The kernel is rebooted before every request but the first, so after this one
        // each measured request starts clean: its count holds only its own queries (not
        // the fixtures' inserts), and no entity is already loaded from the setup.
        $this->client->request('GET', '/books');
    }

    public function testBookListDoesNotQueryPerBook(): void
    {
        $this->createBooks(1);
        $one = $this->queryCount('/books');

        $this->createBooks(5);
        $many = $this->queryCount('/books');

        self::assertCount(6, $this->items());
        self::assertSame($one, $many);
    }

    public function testBooksOfAnAuthorDoNotQueryPerBook(): void
    {
        $author = AuthorFakeDataFactory::createOne();
        $this->createBooks(1, $author);
        $one = $this->queryCount('/authors/'.$author->getId().'/books');

        $this->createBooks(5, $author);
        $many = $this->queryCount('/authors/'.$author->getId().'/books');

        self::assertCount(6, $this->items());
        self::assertSame($one, $many);
    }

    public function testSingleBookDoesNotQueryPerAuthor(): void
    {
        $book = BookFakeDataFactory::createOne();
        BookAuthorFakeDataFactory::createOne(['book_id' => $book, 'author_id' => AuthorFakeDataFactory::createOne()]);
        $one = $this->queryCount('/books/'.$book->getId());

        foreach (range(1, 4) as $i) {
            BookAuthorFakeDataFactory::createOne(['book_id' => $book, 'author_id' => AuthorFakeDataFactory::createOne()]);
        }
        $many = $this->queryCount('/books/'.$book->getId());

        self::assertCount(5, $this->responseData()['authors']);
        self::assertSame($one, $many);
    }

    public function testAuthorsKeepTheOrderTheyWereAddedIn(): void
    {
        $book = BookFakeDataFactory::createOne();
        foreach (['Zoe', 'Adam', 'Mia'] as $name) {
            BookAuthorFakeDataFactory::createOne(['book_id' => $book, 'author_id' => AuthorFakeDataFactory::createOne(['name' => $name])]);
        }

        $this->client->request('GET', '/books');

        self::assertSame(['Zoe', 'Adam', 'Mia'], array_column($this->items()[0]['authors'], 'name'));
    }

    public function testReviewListDoesNotQueryPerReview(): void
    {
        BookReviewFakeDataFactory::createOne(['book_id' => BookFakeDataFactory::createOne()]);
        $one = $this->queryCount('/reviews');

        foreach (range(1, 5) as $i) {
            BookReviewFakeDataFactory::createOne(['book_id' => BookFakeDataFactory::createOne()]);
        }
        $many = $this->queryCount('/reviews');

        self::assertCount(6, $this->items());
        self::assertSame($one, $many);
    }

    /**
     * Books with two authors and a review each; with $shared, one of the authors is
     * the same for every book.
     */
    private function createBooks(int $count, ?object $shared = null): void
    {
        foreach (range(1, $count) as $i) {
            $book = BookFakeDataFactory::createOne();
            BookAuthorFakeDataFactory::createOne(['book_id' => $book, 'author_id' => $shared ?? AuthorFakeDataFactory::createOne()]);
            BookAuthorFakeDataFactory::createOne(['book_id' => $book, 'author_id' => AuthorFakeDataFactory::createOne()]);
            BookReviewFakeDataFactory::createOne(['book_id' => $book]);
        }
    }

    private function queryCount(string $uri): int
    {
        $this->client->enableProfiler();
        $this->client->request('GET', $uri);
        self::assertResponseIsSuccessful();

        $collector = $this->client->getProfile()->getCollector('db');
        self::assertInstanceOf(DoctrineDataCollector::class, $collector);

        return $collector->getQueryCount();
    }
}
