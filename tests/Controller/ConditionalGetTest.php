<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Factory\BookFakeDataFactory;
use App\Tests\ApiTestCase;
use Symfony\Component\HttpFoundation\Response;

class ConditionalGetTest extends ApiTestCase
{
    public function testAGetAnswersWithAnEtagThatOnlyTheClientMayCache(): void
    {
        $this->authenticate();
        $book = BookFakeDataFactory::createOne();

        $this->client->request('GET', '/books/'.$book->getId());

        $response = $this->client->getResponse();
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertNotEmpty($response->getEtag());
        self::assertTrue($response->headers->hasCacheControlDirective('private'));
        self::assertTrue($response->headers->hasCacheControlDirective('no-cache'));
        self::assertFalse($response->headers->hasCacheControlDirective('public'));
    }

    public function testAnUnchangedResourceAnswersNotModifiedWithoutABody(): void
    {
        $this->authenticate();
        $book = BookFakeDataFactory::createOne();

        $this->client->request('GET', '/books/'.$book->getId());
        $etag = (string) $this->client->getResponse()->getEtag();

        $this->client->request('GET', '/books/'.$book->getId(), server: ['HTTP_IF_NONE_MATCH' => $etag]);

        $response = $this->client->getResponse();
        self::assertSame(Response::HTTP_NOT_MODIFIED, $response->getStatusCode());
        self::assertSame('', $response->getContent());
        self::assertSame($etag, $response->getEtag());
    }

    public function testAChangedResourceAnswersWithTheNewBody(): void
    {
        $this->authenticate(['ROLE_ADMIN']);
        $book = BookFakeDataFactory::createOne(['title' => 'Before']);

        $this->client->request('GET', '/books/'.$book->getId());
        $etag = (string) $this->client->getResponse()->getEtag();

        $this->requestJson('PATCH', '/books/'.$book->getId(), ['title' => 'After']);
        self::assertResponseIsSuccessful();

        $this->client->request('GET', '/books/'.$book->getId(), server: ['HTTP_IF_NONE_MATCH' => $etag]);

        $response = $this->client->getResponse();
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertNotSame($etag, $response->getEtag());
        self::assertSame('After', json_decode((string) $response->getContent(), true)['title']);
    }

    public function testListsAreConditionalToo(): void
    {
        $this->authenticate();
        BookFakeDataFactory::createMany(2);

        $this->client->request('GET', '/books');
        $etag = (string) $this->client->getResponse()->getEtag();

        $this->client->request('GET', '/books', server: ['HTTP_IF_NONE_MATCH' => $etag]);

        self::assertSame(Response::HTTP_NOT_MODIFIED, $this->client->getResponse()->getStatusCode());
    }

    public function testErrorsAndWritesGetNoEtag(): void
    {
        $this->authenticate();

        $this->client->request('GET', '/books/999999');
        self::assertSame(Response::HTTP_NOT_FOUND, $this->client->getResponse()->getStatusCode());
        self::assertNull($this->client->getResponse()->getEtag());

        $this->requestJson('POST', '/register', ['email' => 'etag@example.com', 'password' => 'long enough']);
        self::assertSame(Response::HTTP_CREATED, $this->client->getResponse()->getStatusCode());
        self::assertNull($this->client->getResponse()->getEtag());
    }
}
