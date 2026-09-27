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
