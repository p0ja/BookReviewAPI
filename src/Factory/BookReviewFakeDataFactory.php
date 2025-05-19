<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Book;
use App\Entity\Review;
use App\Repository\AuthorRepository;
use App\Repository\BookRepository;
use DateTimeImmutable;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<Book>
 */
final class BookReviewFakeDataFactory extends PersistentProxyObjectFactory
{
    public function __construct(
        private readonly BookRepository $bookRepository,
    ) {
    }

    public static function class(): string
    {
        return Review::class;
    }

    protected function defaults(): array|callable
    {
        return [
            'name' => self::faker()->name,
            'book_id' => self::faker()->randomElement($this->bookRepository->findAll()),
            'content' => self::faker()->realText(500),
            'rating' => self::faker()->numberBetween(0, 5),
            'submit_date' => DateTimeImmutable::createFromMutable(self::faker()->dateTimeBetween('-3 years', 'now')),
        ];
    }
}
