<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Dto\CreateAuthor;
use App\Factory\AuthorFakeDataFactory;
use App\Factory\BookFakeDataFactory;
use App\Repository\AuthorRepository;
use App\Repository\BookAuthorRepository;

class AuthorRepositoryTest extends RepositoryTestCase
{
    public function testFindOrCreateReusesAnAuthorWhateverTheCaseAndSpacing(): void
    {
        $repository = $this->service(AuthorRepository::class);

        $first = $repository->findOrCreate(new CreateAuthor('  Martin Fowler '));
        $second = $repository->findOrCreate(new CreateAuthor('martin FOWLER'));

        self::assertSame($first->getId(), $second->getId());
        self::assertSame('Martin Fowler', $first->getName());
        AuthorFakeDataFactory::assert()->count(1);
    }

    /**
     * Anyone may create a book, and authors are shared by every book.
     */
    public function testFindOrCreateLeavesAnExistingAuthorsInfoAlone(): void
    {
        $repository = $this->service(AuthorRepository::class);
        $repository->findOrCreate(new CreateAuthor('Martin Fowler', 'Chief Scientist'));

        self::assertSame('Chief Scientist', $repository->findOrCreate(new CreateAuthor('Martin Fowler'))->getInfo());
        self::assertSame('Chief Scientist', $repository->findOrCreate(new CreateAuthor('Martin Fowler', 'Vandal'))->getInfo());
    }

    public function testFindOrCreateUpdatesTheInfoOnlyWhenAllowedAndGiven(): void
    {
        $repository = $this->service(AuthorRepository::class);
        $repository->findOrCreate(new CreateAuthor('Martin Fowler', 'Chief Scientist'));

        self::assertSame('Chief Scientist', $repository->findOrCreate(new CreateAuthor('Martin Fowler'), updateInfo: true)->getInfo());
        self::assertSame('Author of Refactoring', $repository->findOrCreate(new CreateAuthor('Martin Fowler', 'Author of Refactoring'), updateInfo: true)->getInfo());
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
