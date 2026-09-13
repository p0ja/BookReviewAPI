<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Dto\CreateBook;
use App\Factory\BookFakeDataFactory;
use App\Repository\BookRepository;

class BookRepositoryTest extends RepositoryTestCase
{
    public function testCreateBookNormalisesTheInput(): void
    {
        $book = $this->service(BookRepository::class)->createBook($this->createBook(isbn: ' 9780134494166 ', price: '29.987'));

        self::assertNotNull($book->getId());
        self::assertNotNull($book->getCreatedAt());
        self::assertSame('Clean Architecture', $book->getTitle());
        self::assertSame('9780134494166', $book->getIsbn());
        self::assertSame('Software', $book->getGenre());
        self::assertSame('2017-09-10', $book->getPublishDate());
        self::assertSame(29.99, $book->getPrice());
    }

    public function testCreateBookWithAKnownIsbnUpdatesTheExistingBook(): void
    {
        $existing = BookFakeDataFactory::createOne(['isbn' => '9780134494166', 'title' => 'Old title']);

        $book = $this->service(BookRepository::class)->createBook($this->createBook(isbn: ' 9780134494166'));

        self::assertSame($existing->getId(), $book->getId());
        BookFakeDataFactory::assert()->count(1);
        BookFakeDataFactory::assert()->exists(['title' => 'Clean Architecture']);
    }

    public function testFindBooksAppliesPaginationAndSorting(): void
    {
        foreach (['D', 'B', 'A', 'C'] as $title) {
            BookFakeDataFactory::createOne(['title' => $title]);
        }

        $books = $this->service(BookRepository::class)->findBooks(2, 2, 'title');

        self::assertSame(['C', 'D'], array_map(static fn ($book) => $book->getTitle(), $books));
    }

    public function testRemoveBookReportsWhetherABookWasDeleted(): void
    {
        $id = BookFakeDataFactory::createOne()->getId();
        $repository = $this->service(BookRepository::class);

        self::assertTrue($repository->removeBook($id));
        self::assertFalse($repository->removeBook($id));
        BookFakeDataFactory::assert()->empty();
    }

    private function createBook(string $isbn = '9780134494166', string $price = '29.99'): CreateBook
    {
        return new CreateBook(
            title: ' Clean Architecture ',
            isbn: $isbn,
            description: 'A craftsman\'s guide',
            price: $price,
            genre: ' Software ',
            publish_date: ' 2017-09-10 ',
        );
    }
}
