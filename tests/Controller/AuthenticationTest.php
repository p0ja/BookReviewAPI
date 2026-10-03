<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Factory\UserFakeDataFactory;
use App\Tests\ApiTestCase;
use App\Tests\BackdateTokensListener;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
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

    /**
     * Under WSL2 the wall clock briefly runs about a minute ahead; a token issued then
     * used to be rejected by the next request. BackdateTokensListener issues every test
     * token in the past, while it still expires a full TTL from now.
     */
    public function testATokenIssuedWhileTheClockRanAheadIsAccepted(): void
    {
        $user = UserFakeDataFactory::createOne()->_real();
        $manager = static::getContainer()->get(JWTTokenManagerInterface::class);

        $token = $manager->createFromPayload($user, ['iat' => time() + 64]);

        // Compared with each other, not with time(): the clock may step between issuing
        // the token and asserting, which is what this test is about. The listener and
        // Lexik read the clock a moment apart, hence the second of slack.
        $payload = $manager->parse($token);
        self::assertGreaterThanOrEqual(3600 + BackdateTokensListener::BACKDATE_SECONDS - 1, $payload['exp'] - $payload['iat']);

        $this->client->request('GET', '/books', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$token]);
        self::assertResponseIsSuccessful();
    }

    public function testWrongPasswordIsRejected(): void
    {
        UserFakeDataFactory::createOne(['email' => 'reader@example.com', 'password' => 'secret']);

        $this->requestJson('POST', '/login_check', ['username' => 'reader@example.com', 'password' => 'wrong']);

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        self::assertSame(['error' => 'Invalid credentials.'], $this->responseData());
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
        self::assertResponseHeaderSame('WWW-Authenticate', 'Bearer');
        self::assertSame(['error' => 'JWT Token not found'], $this->responseData());
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
        yield 'delete book' => ['DELETE', '/books/1'];
        yield 'list reviews' => ['GET', '/reviews'];
        yield 'delete review' => ['DELETE', '/reviews/1'];
        yield 'get review' => ['GET', '/reviews/1'];
        yield 'list authors' => ['GET', '/authors'];
        yield 'get author' => ['GET', '/authors/1'];
        yield 'update author' => ['PATCH', '/authors/1'];
        yield 'author books' => ['GET', '/authors/1/books'];
    }

    public function testInvalidTokenIsRejected(): void
    {
        $this->client->request('GET', '/books', server: ['HTTP_AUTHORIZATION' => 'Bearer not-a-jwt']);

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        self::assertSame(['error' => 'Invalid JWT Token'], $this->responseData());
    }

    public function testRepeatedFailedLoginsAreThrottled(): void
    {
        UserFakeDataFactory::createOne(['email' => 'reader@example.com', 'password' => 'secret']);
        // One kernel for every request, so the limiter state is kept between them.
        $this->client->disableReboot();

        for ($attempt = 1; $attempt <= 5; ++$attempt) {
            $this->requestJson('POST', '/login_check', ['username' => 'reader@example.com', 'password' => 'wrong']);
            self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        }

        // Blocked now, even with the right password.
        $this->requestJson('POST', '/login_check', ['username' => 'reader@example.com', 'password' => 'secret']);

        self::assertResponseStatusCodeSame(Response::HTTP_TOO_MANY_REQUESTS);
        self::assertStringStartsWith('Too many failed login attempts', $this->responseData()['error']);
        // The interval is one minute: retry within at most 60 seconds, as with /register.
        self::assertSame('60', $this->client->getResponse()->headers->get('Retry-After'));
    }

    public function testFailedLoginsBelowTheLimitHaveNoRetryAfter(): void
    {
        UserFakeDataFactory::createOne(['email' => 'reader@example.com', 'password' => 'secret']);

        $this->requestJson('POST', '/login_check', ['username' => 'reader@example.com', 'password' => 'wrong']);

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        self::assertFalse($this->client->getResponse()->headers->has('Retry-After'));
    }
}
