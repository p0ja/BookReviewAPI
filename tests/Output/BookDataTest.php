<?php

declare(strict_types=1);

namespace App\Tests\Output;

use App\Entity\Author;
use App\Entity\Book;
use App\Entity\BookAuthor;
use App\Output\BookData;
use App\Repository\ReviewRepository;
use PHPUnit\Framework\TestCase;

class BookDataTest extends TestCase
{
    public function testOutputContainsTheBookFieldsAndAuthorNames(): void
    {
        $book = (new Book())
            ->setId(3)
            ->setTitle('Refactoring')
            ->setIsbn('9780134757599')
            ->setPrice('47.50')
            ->setDescription('Improving the design of existing code')
            ->setGenre('Software')
            ->setPublishDate(new \DateTimeImmutable('2018-11-20'));
        $book->addBookAuthor((new BookAuthor())->setAuthor((new Author())->setId(8)->setName('Martin Fowler')));

        self::assertSame([
            'id' => 3,
            'title' => 'Refactoring',
            'isbn' => '9780134757599',
            'price' => 47.5,
            'description' => 'Improving the design of existing code',
            'genre' => 'Software',
            'publish_date' => '2018-11-20',
            'authors' => [
                ['id' => 8, 'name' => 'Martin Fowler'],
            ],
            'average_rating' => 4.5,
            'review_count' => 2,
        ], $this->bookData()->getOutput($book, ['average' => 4.5, 'count' => 2]));
    }

    public function testBookWithoutAuthorsHasAnEmptyAuthorList(): void
    {
        $output = $this->bookData()->getOutput((new Book())->setTitle('Anonymous'));

        self::assertSame([], $output['authors']);
        self::assertNull($output['average_rating']);
        self::assertSame(0, $output['review_count']);
    }

    public function testLinkWithoutAnAuthorHasANullName(): void
    {
        $book = new Book();
        $book->addBookAuthor(new BookAuthor());

        self::assertSame([['id' => null, 'name' => null]], $this->bookData()->getOutput($book)['authors']);
    }

    private function bookData(): BookData
    {
        return new BookData(self::createStub(ReviewRepository::class));
    }
}
