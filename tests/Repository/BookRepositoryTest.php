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
        self::assertSame('2017-09-10', $book->getPublishDate()?->format('Y-m-d'));
        self::assertSame('29.99', $book->getPrice());
    }

    public function testIsbnExistsIgnoresSurroundingWhitespace(): void
    {
        BookFakeDataFactory::createOne(['isbn' => '9780134494166']);
        $repository = $this->service(BookRepository::class);

        self::assertTrue($repository->isbnExists(' 9780134494166 '));
        self::assertFalse($repository->isbnExists('9780321125215'));
    }

    public function testFindBooksAppliesPaginationAndSorting(): void
    {
        foreach (['D', 'B', 'A', 'C'] as $title) {
            BookFakeDataFactory::createOne(['title' => $title]);
        }

        $books = $this->service(BookRepository::class)->findBooks(2, 2, 'title');

        self::assertSame(['C', 'D'], array_map(static fn ($book) => $book->getTitle(), $books->items));
        self::assertSame([4, 2, 2], [$books->total, $books->page, $books->size]);
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
            publish_date: '2017-09-10',
        );
    }
}
