<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * The ExceptionListener rewrites every error response, so these cases assert it
 * preserves the real status code and always answers with JSON.
 */
class ErrorResponseTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->catchExceptions(true);
    }

    public function testUnauthenticatedRequestIsRejectedWithJson(): void
    {
        $this->client->request('GET', '/books');

        self::assertSame(Response::HTTP_UNAUTHORIZED, $this->client->getResponse()->getStatusCode());
        self::assertJson($this->client->getResponse()->getContent());
    }

    public function testUnknownRouteIsNotFoundWithJson(): void
    {
        $this->client->request('GET', '/no-such-endpoint');

        $response = $this->client->getResponse();

        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        self::assertStringContainsString('application/json', (string) $response->headers->get('Content-Type'));
        self::assertArrayHasKey('error', (array) json_decode((string) $response->getContent(), true));
    }

    /**
     * Statuses outside the listener's explicit match arms used to fall through to an
     * empty text/html body, so assert the body is real JSON, not just the status.
     */
    public function testWrongMethodIsMethodNotAllowedWithJsonBody(): void
    {
        $this->client->request('PUT', '/books');

        $response = $this->client->getResponse();

        self::assertSame(Response::HTTP_METHOD_NOT_ALLOWED, $response->getStatusCode());
        self::assertTrue($response->headers->has('Allow'));
        self::assertStringContainsString('application/json', (string) $response->headers->get('Content-Type'));
        self::assertArrayHasKey('error', (array) json_decode((string) $response->getContent(), true));
    }
}
