<?php

namespace App\Factory;

use App\Entity\BookAuthor;
use App\Repository\AuthorRepository;
use App\Repository\BookRepository;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<BookAuthor>
 */
final class BookAuthorFactory extends PersistentProxyObjectFactory
{
    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#factories-as-services
     *
     * @todo inject services if required
     */
    public function __construct(
        private readonly AuthorRepository $authorRepository,
        private readonly BookRepository $bookRepository,
    ) {
    }

    public static function class(): string
    {
        return BookAuthor::class;
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#model-factories
     *
     * @todo add your default values here
     */
    protected function defaults(): array|callable
    {
        return [
            'book_id' => self::faker()->randomElement($this->bookRepository->findAll()),
            'author_id' => self::faker()->randomElement($this->authorRepository->findAll()),
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    protected function initialize(): static
    {
        return $this
            // ->afterInstantiate(function(BookAuthor $bookAuthor): void {})
        ;
    }
}
