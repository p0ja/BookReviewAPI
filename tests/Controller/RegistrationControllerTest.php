<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Factory\UserFakeDataFactory;
use App\Tests\ApiTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;

class RegistrationControllerTest extends ApiTestCase
{
    public function testNewAccountCanLogInAndUseTheApi(): void
    {
        $this->requestJson('POST', '/register', ['email' => ' reader@example.com ', 'password' => 'long enough']);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $data = $this->responseData();
        self::assertIsInt($data['id']);
        self::assertSame('reader@example.com', $data['email']);
        self::assertArrayNotHasKey('password', $data);

        $this->requestJson('POST', '/login_check', ['username' => 'reader@example.com', 'password' => 'long enough']);
        self::assertResponseIsSuccessful();
        $token = $this->responseData()['token'];

        $this->client->request('GET', '/books', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$token]);
        self::assertResponseIsSuccessful();
    }

    public function testNewAccountIsNotAnAdmin(): void
    {
        $this->requestJson('POST', '/register', ['email' => 'reader@example.com', 'password' => 'long enough']);

        UserFakeDataFactory::assert()->exists(['email' => 'reader@example.com']);
        $user = UserFakeDataFactory::find(['email' => 'reader@example.com']);
        self::assertSame(['ROLE_USER'], $user->getRoles());
        self::assertNotSame('long enough', $user->getPassword());
    }

    public function testTakenEmailIsAConflict(): void
    {
        UserFakeDataFactory::createOne(['email' => 'reader@example.com']);

        $this->requestJson('POST', '/register', ['email' => 'reader@example.com', 'password' => 'long enough']);

        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        self::assertSame(['error' => 'An account with this email already exists'], $this->responseData());
        UserFakeDataFactory::assert()->count(1);
    }

    public function testEmailIsStoredInLowerCase(): void
    {
        $this->requestJson('POST', '/register', ['email' => 'Reader@Example.COM', 'password' => 'long enough']);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        self::assertSame('reader@example.com', $this->responseData()['email']);
        UserFakeDataFactory::assert()->exists(['email' => 'reader@example.com']);
    }

    public function testEmailTakenInAnotherCaseIsAConflict(): void
    {
        // An account from before emails were normalised.
        UserFakeDataFactory::createOne(['email' => 'Reader@example.com']);

        $this->requestJson('POST', '/register', ['email' => 'reader@EXAMPLE.com', 'password' => 'long enough']);

        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        UserFakeDataFactory::assert()->count(1);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function loginSpellings(): iterable
    {
        yield 'registered lower case, logs in with capitals' => ['reader@example.com', 'READER@Example.com'];
        yield 'older mixed-case account, logs in in lower case' => ['Reader@Example.com', 'reader@example.com'];
    }

    #[DataProvider('loginSpellings')]
    public function testLoginIgnoresTheCaseOfTheEmail(string $stored, string $typed): void
    {
        UserFakeDataFactory::createOne(['email' => $stored, 'password' => 'long enough']);

        $this->requestJson('POST', '/login_check', ['username' => $typed, 'password' => 'long enough']);

        self::assertResponseIsSuccessful();
        $token = $this->responseData()['token'];

        // The token names the account as stored, so it keeps working.
        $this->client->request('GET', '/books', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$token]);
        self::assertResponseIsSuccessful();
    }

    public function testAccountsDifferingOnlyByCaseKeepTheirOwnLogin(): void
    {
        UserFakeDataFactory::createOne(['email' => 'reader@example.com', 'password' => 'lower password']);
        UserFakeDataFactory::createOne(['email' => 'Reader@example.com', 'password' => 'upper password']);

        $this->requestJson('POST', '/login_check', ['username' => 'Reader@example.com', 'password' => 'upper password']);
        self::assertResponseIsSuccessful();

        // The exact spelling wins, so the other account's password does not work here.
        $this->requestJson('POST', '/login_check', ['username' => 'Reader@example.com', 'password' => 'lower password']);
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testRegistrationsFromOneAddressAreLimited(): void
    {
        for ($i = 1; $i <= 10; ++$i) {
            $this->requestJson('POST', '/register', ['email' => "reader$i@example.com", 'password' => 'long enough']);
            self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        }

        $this->requestJson('POST', '/register', ['email' => 'reader11@example.com', 'password' => 'long enough']);

        self::assertResponseStatusCodeSame(Response::HTTP_TOO_MANY_REQUESTS);
        self::assertSame(['error' => 'Too Many Requests'], $this->responseData());
        self::assertGreaterThan(0, (int) $this->client->getResponse()->headers->get('Retry-After'));
        UserFakeDataFactory::assert()->count(10);
    }

    public function testFailedRegistrationsCountTowardsTheLimit(): void
    {
        UserFakeDataFactory::createOne(['email' => 'taken@example.com']);
        for ($i = 1; $i <= 10; ++$i) {
            $this->requestJson('POST', '/register', ['email' => 'taken@example.com', 'password' => 'long enough']);
            self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        }

        // Probing which emails are registered is limited too.
        $this->requestJson('POST', '/register', ['email' => 'taken@example.com', 'password' => 'long enough']);

        self::assertResponseStatusCodeSame(Response::HTTP_TOO_MANY_REQUESTS);
    }

    /**
     * @return iterable<string, array{array<string, string>, string}>
     */
    public static function invalidRegistrations(): iterable
    {
        yield 'not an email' => [['email' => 'reader', 'password' => 'long enough'], 'email'];
        yield 'short password' => [['email' => 'reader@example.com', 'password' => 'short'], 'password'];
        yield 'missing password' => [['email' => 'reader@example.com'], 'password'];
    }

    /**
     * @param array<string, string> $payload
     */
    #[DataProvider('invalidRegistrations')]
    public function testInvalidRegistrationIsRejected(array $payload, string $field): void
    {
        $this->requestJson('POST', '/register', $payload);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertStringContainsString($field, $this->responseData()['error']);
        UserFakeDataFactory::assert()->empty();
    }
}
