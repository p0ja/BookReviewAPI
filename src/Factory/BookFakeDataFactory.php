<?php

namespace App\Factory;

use App\Entity\Book;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<Book>
 */
final class BookFakeDataFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return Book::class;
    }

    protected function defaults(): array|callable
    {
        return [
            'title' => self::faker()->sentence(5),
            'isbn' => self::faker()->isbn13(),
            'price' => self::faker()->randomFloat(),
            'description' => self::faker()->realText(200),
            'genre' => self::faker()->word,
            'publish_date' => self::faker()->date(),
        ];
    }
}
