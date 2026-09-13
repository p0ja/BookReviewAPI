<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Factory\UserFakeDataFactory;
use App\Tests\ApiTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;

class AuthenticationTest extends ApiTestCase
{
    public function testDatabaseUserReceivesATokenThatOpensTheApi(): void
    {
        UserFakeDataFactory::createOne(['email' => 'reader@example.com', 'password' => 'secret']);

        $this->requestJson('POST', '/login_check', ['username' => 'reader@example.com', 'password' => 'secret']);

        self::assertResponseIsSuccessful();
        $token = $this->responseData()['token'] ?? null;
        self::assertIsString($token);

        $this->client->request('GET', '/books', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$token]);

        self::assertResponseIsSuccessful();
    }

    public function testWrongPasswordIsRejected(): void
    {
        UserFakeDataFactory::createOne(['email' => 'reader@example.com', 'password' => 'secret']);

        $this->requestJson('POST', '/login_check', ['username' => 'reader@example.com', 'password' => 'wrong']);

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testUnknownUserIsRejected(): void
    {
        $this->requestJson('POST', '/login_check', ['username' => 'nobody@example.com', 'password' => 'secret']);

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    #[DataProvider('protectedEndpoints')]
    public function testEndpointRequiresAToken(string $method, string $uri): void
    {
        $this->client->request($method, $uri);

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function protectedEndpoints(): iterable
    {
        yield 'list books' => ['GET', '/books'];
        yield 'get book' => ['GET', '/books/1'];
        yield 'create book' => ['POST', '/books'];
        yield 'book reviews' => ['GET', '/books/1/reviews'];
        yield 'create review' => ['POST', '/books/1/reviews'];
        yield 'delete book' => ['DELETE', '/book/delete/1'];
        yield 'list reviews' => ['GET', '/reviews'];
        yield 'delete review' => ['DELETE', '/review/delete/1'];
    }

    public function testInvalidTokenIsRejected(): void
    {
        $this->client->request('GET', '/books', server: ['HTTP_AUTHORIZATION' => 'Bearer not-a-jwt']);

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }
}
