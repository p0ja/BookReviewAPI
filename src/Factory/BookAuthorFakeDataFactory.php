<?php

namespace App\Factory;

use App\Entity\BookAuthor;
use App\Repository\AuthorRepository;
use App\Repository\BookRepository;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<BookAuthor>
 */
final class BookAuthorFakeDataFactory extends PersistentProxyObjectFactory
{
    public function __construct(
        private readonly AuthorRepository $authorRepository,
        private readonly BookRepository $bookRepository,
    ) {
    }

    public static function class(): string
    {
        return BookAuthor::class;
    }

    protected function defaults(): array|callable
    {
        return [
            'book_id' => self::faker()->randomElement($this->bookRepository->findAll()),
            'author_id' => self::faker()->randomElement($this->authorRepository->findAll()),
        ];
    }
}
