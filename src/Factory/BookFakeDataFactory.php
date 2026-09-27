<?php

declare(strict_types=1);

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
            'price' => (string) self::faker()->randomFloat(2, 1, 200),
            'description' => self::faker()->realText(200),
            'genre' => self::faker()->word(),
            'publish_date' => \DateTimeImmutable::createFromMutable(self::faker()->dateTimeBetween('-30 years')),
        ];
    }
}
