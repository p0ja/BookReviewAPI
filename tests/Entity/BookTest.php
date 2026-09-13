<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Author;
use App\Entity\Book;
use App\Entity\BookAuthor;
use App\Entity\Review;
use PHPUnit\Framework\TestCase;

class BookTest extends TestCase
{
    public function testCreationTimeIsSetWhenTheBookIsFirstPersisted(): void
    {
        $book = new Book();
        self::assertNull($book->getCreatedAt());

        $before = new \DateTimeImmutable();
        $book->onPrePersist();

        self::assertGreaterThanOrEqual($before, $book->getCreatedAt());
    }

    public function testAddingABookAuthorLinksBothSidesOnce(): void
    {
        $book = new Book();
        $bookAuthor = new BookAuthor();

        $book->addBookAuthor($bookAuthor)->addBookAuthor($bookAuthor);

        self::assertCount(1, $book->getBookAuthors());
        self::assertSame($book, $bookAuthor->getBook());
    }

    public function testRemovingABookAuthorUnlinksIt(): void
    {
        $book = new Book();
        $bookAuthor = new BookAuthor();
        $book->addBookAuthor($bookAuthor);

        $book->removeBookAuthor($bookAuthor);

        self::assertCount(0, $book->getBookAuthors());
        self::assertNull($bookAuthor->getBook());
    }

    public function testRemovingABookAuthorKeepsALinkThatWasMovedToAnotherBook(): void
    {
        $book = new Book();
        $otherBook = new Book();
        $bookAuthor = new BookAuthor();
        $book->addBookAuthor($bookAuthor);
        $bookAuthor->setBook($otherBook);

        $book->removeBookAuthor($bookAuthor);

        self::assertSame($otherBook, $bookAuthor->getBook());
    }

    public function testAddingAReviewLinksBothSidesOnce(): void
    {
        $book = new Book();
        $review = new Review();

        $book->addReview($review)->addReview($review);

        self::assertCount(1, $book->getReviews());
        self::assertSame($book, $review->getBook());
    }

    public function testRemovingAReviewUnlinksIt(): void
    {
        $book = new Book();
        $review = new Review();
        $book->addReview($review);

        $book->removeReview($review);

        self::assertCount(0, $book->getReviews());
        self::assertNull($review->getBook());
    }

    public function testAuthorBooksStayInSyncWithTheLink(): void
    {
        $author = new Author();
        $bookAuthor = new BookAuthor();

        $author->addAuthorBook($bookAuthor)->addAuthorBook($bookAuthor);
        self::assertCount(1, $author->getAuthorBooks());
        self::assertSame($author, $bookAuthor->getAuthor());

        $author->removeAuthorBook($bookAuthor);
        self::assertCount(0, $author->getAuthorBooks());
        self::assertNull($bookAuthor->getAuthor());
    }
}
