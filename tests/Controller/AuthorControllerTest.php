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
}
