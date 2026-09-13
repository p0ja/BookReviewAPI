<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Factory\AuthorFakeDataFactory;
use App\Factory\BookFakeDataFactory;
use App\Repository\AuthorRepository;
use App\Repository\BookAuthorRepository;

class AuthorRepositoryTest extends RepositoryTestCase
{
    public function testCreateAuthorReusesAnAuthorWithTheSameName(): void
    {
        $repository = $this->service(AuthorRepository::class);

        $first = $repository->createAuthor(['name' => 'Martin Fowler', 'info' => null]);
        $second = $repository->createAuthor(['name' => 'Martin Fowler', 'info' => 'Chief Scientist']);

        self::assertSame($first->getId(), $second->getId());
        self::assertSame('Chief Scientist', $second->getInfo());
        AuthorFakeDataFactory::assert()->count(1);
    }

    public function testCreateBookAuthorDoesNotDuplicateAnExistingLink(): void
    {
        $book = BookFakeDataFactory::createOne()->_real();
        $author = AuthorFakeDataFactory::createOne()->_real();
        $repository = $this->service(BookAuthorRepository::class);

        $first = $repository->createBookAuthor($book, $author);
        $second = $repository->createBookAuthor($book, $author);

        self::assertSame($first->getId(), $second->getId());
        self::assertSame($book, $second->getBook());
        self::assertSame($author, $second->getAuthor());
    }
}
