<?php

namespace App\Factory;

use App\Entity\Author;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<Author>
 */
final class AuthorFakeDataFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return Author::class;
    }

    protected function defaults(): array|callable
    {
        return [
            'name' => self::faker()->name,
            'info' => self::faker()->text(255),
        ];
    }
}
