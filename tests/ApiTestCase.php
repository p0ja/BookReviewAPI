<?php

declare(strict_types=1);

namespace App\Tests;

use App\Factory\UserFakeDataFactory;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Base for tests that call the API through the kernel against the test database.
 */
abstract class ApiTestCase extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    protected KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    /**
     * Sends every following request with a valid JWT for a freshly created user.
     */
    protected function authenticate(): void
    {
        $user = UserFakeDataFactory::createOne()->_real();
        $token = static::getContainer()->get(JWTTokenManagerInterface::class)->create($user);

        $this->client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$token);
    }

    /**
     * @param array<mixed> $payload
     */
    protected function requestJson(string $method, string $uri, array $payload = []): void
    {
        $this->client->request(
            $method,
            $uri,
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode($payload, JSON_THROW_ON_ERROR),
        );
    }

    /**
     * @return array<mixed>
     */
    protected function responseData(): array
    {
        return json_decode((string) $this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
    }
}
