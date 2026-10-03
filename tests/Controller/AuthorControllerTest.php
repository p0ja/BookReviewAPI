<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Factory\AuthorFakeDataFactory;
use App\Factory\BookAuthorFakeDataFactory;
use App\Factory\BookFakeDataFactory;
use App\Tests\ApiTestCase;
use Symfony\Component\HttpFoundation\Response;

class AuthorControllerTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->authenticate();
    }

    public function testListIsPaginatedAndSortedByName(): void
    {
        foreach (['Martin Fowler', 'Eric Evans', 'Kent Beck'] as $name) {
            AuthorFakeDataFactory::createOne(['name' => $name, 'info' => null]);
        }

        $this->client->request('GET', '/authors?orderBy=name&size=2');

        self::assertResponseIsSuccessful();
        self::assertSame(['Eric Evans', 'Kent Beck'], array_column($this->items(), 'name'));
        self::assertSame(3, $this->responseData()['total']);
    }

    public function testListCanBeFilteredByName(): void
    {
        AuthorFakeDataFactory::createOne(['name' => 'Martin Fowler']);
        AuthorFakeDataFactory::createOne(['name' => 'Robert C. Martin']);
        AuthorFakeDataFactory::createOne(['name' => 'Kent Beck']);

        $this->client->request('GET', '/authors?orderBy=name&name=MARTIN');

        self::assertSame(['Martin Fowler', 'Robert C. Martin'], array_column($this->items(), 'name'));
    }

    public function testGetReturnsASingleAuthor(): void
    {
        $author = AuthorFakeDataFactory::createOne(['name' => 'Kent Beck', 'info' => 'Extreme Programming']);

        $this->client->request('GET', '/authors/'.$author->getId());

        self::assertResponseIsSuccessful();
        self::assertSame(['id' => $author->getId(), 'name' => 'Kent Beck', 'info' => 'Extreme Programming'], $this->responseData());
    }

    public function testGetUnknownAuthorIsNotFound(): void
    {
        $this->client->request('GET', '/authors/999999');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testBooksListsOnlyThatAuthorsBooks(): void
    {
        $fowler = AuthorFakeDataFactory::createOne(['name' => 'Martin Fowler']);
        foreach (['Refactoring', 'Analysis Patterns'] as $title) {
            BookAuthorFakeDataFactory::createOne(['book' => BookFakeDataFactory::createOne(['title' => $title]), 'author' => $fowler]);
        }
        BookAuthorFakeDataFactory::createOne([
            'book' => BookFakeDataFactory::createOne(['title' => 'Domain-Driven Design']),
            'author' => AuthorFakeDataFactory::createOne(['name' => 'Eric Evans']),
        ]);

        $this->client->request('GET', '/authors/'.$fowler->getId().'/books?orderBy=title');

        self::assertResponseIsSuccessful();
        self::assertSame(['Analysis Patterns', 'Refactoring'], array_column($this->items(), 'title'));
        self::assertSame(2, $this->responseData()['total']);
    }

    public function testBooksOfAnUnknownAuthorIsNotFound(): void
    {
        $this->client->request('GET', '/authors/999999/books');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testAdminsUpdateAnAuthor(): void
    {
        $this->authenticate(['ROLE_ADMIN']);
        $author = AuthorFakeDataFactory::createOne(['name' => 'Robert Martin', 'info' => 'Uncle Bob']);

        $this->requestJson('PATCH', '/authors/'.$author->getId(), ['name' => ' Robert C. Martin ']);

        self::assertResponseIsSuccessful();
        self::assertSame(['id' => $author->getId(), 'name' => 'Robert C. Martin', 'info' => 'Uncle Bob'], $this->responseData());

        $this->requestJson('PATCH', '/authors/'.$author->getId(), ['info' => '']);

        self::assertResponseIsSuccessful();
        self::assertNull($this->responseData()['info']);
    }

    public function testAuthorsCanBeUpdatedOnlyByAdmins(): void
    {
        $author = AuthorFakeDataFactory::createOne(['name' => 'Robert C. Martin', 'info' => 'Uncle Bob']);

        $this->requestJson('PATCH', '/authors/'.$author->getId(), ['info' => 'Vandal']);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        self::assertSame('Uncle Bob', $author->_refresh()->getInfo());
    }

    public function testRenamingToAnotherAuthorsNameIsAConflict(): void
    {
        $this->authenticate(['ROLE_ADMIN']);
        AuthorFakeDataFactory::createOne(['name' => 'Kent Beck']);
        $author = AuthorFakeDataFactory::createOne(['name' => 'Martin Fowler']);

        $this->requestJson('PATCH', '/authors/'.$author->getId(), ['name' => 'KENT beck']);
        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);

        // Its own name in another letter case is not a conflict.
        $this->requestJson('PATCH', '/authors/'.$author->getId(), ['name' => 'martin fowler']);
        self::assertResponseIsSuccessful();
    }

    public function testUpdatingAnUnknownAuthorIsNotFound(): void
    {
        $this->authenticate(['ROLE_ADMIN']);

        $this->requestJson('PATCH', '/authors/999999', ['info' => 'x']);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testUpdatingAnAuthorRejectsABlankName(): void
    {
        $this->authenticate(['ROLE_ADMIN']);
        $author = AuthorFakeDataFactory::createOne();

        $this->requestJson('PATCH', '/authors/'.$author->getId(), ['name' => '   ']);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
