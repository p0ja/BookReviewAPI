<?php

declare(strict_types=1);

namespace App\Tests\Output;

use App\Entity\Author;
use App\Entity\Book;
use App\Entity\BookAuthor;
use App\Output\BookData;
use PHPUnit\Framework\TestCase;

class BookDataTest extends TestCase
{
    public function testOutputContainsTheBookFieldsAndAuthorNames(): void
    {
        $book = (new Book())
            ->setId(3)
            ->setTitle('Refactoring')
            ->setIsbn('9780134757599')
            ->setPrice(47.5)
            ->setDescription('Improving the design of existing code')
            ->setGenre('Software')
            ->setPublishDate('2018-11-20');
        $book->addBookAuthor((new BookAuthor())->setAuthor((new Author())->setName('Martin Fowler')));

        self::assertSame([
            'id' => 3,
            'title' => 'Refactoring',
            'isbn' => '9780134757599',
            'price' => 47.5,
            'description' => 'Improving the design of existing code',
            'genre' => 'Software',
            'publish_date' => '2018-11-20',
            'authors' => [
                ['id' => null, 'name' => 'Martin Fowler'],
            ],
        ], (new BookData())->getOutput($book));
    }

    public function testBookWithoutAuthorsHasAnEmptyAuthorList(): void
    {
        $output = (new BookData())->getOutput((new Book())->setTitle('Anonymous'));

        self::assertSame([], $output['authors']);
    }

    public function testLinkWithoutAnAuthorHasANullName(): void
    {
        $book = new Book();
        $book->addBookAuthor(new BookAuthor());

        self::assertSame([['id' => null, 'name' => null]], (new BookData())->getOutput($book)['authors']);
    }
}
