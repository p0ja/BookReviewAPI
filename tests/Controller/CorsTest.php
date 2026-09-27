<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Tests\ApiTestCase;

class CorsTest extends ApiTestCase
{
    public function testPreflightFromLocalhostIsAllowedWithoutAToken(): void
    {
        $this->client->request('OPTIONS', '/books', server: [
            'HTTP_ORIGIN' => 'http://localhost:5173',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'PATCH',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'authorization, content-type',
        ]);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Access-Control-Allow-Origin', 'http://localhost:5173');
        self::assertStringContainsString('PATCH', (string) $this->client->getResponse()->headers->get('Access-Control-Allow-Methods'));
        self::assertStringContainsString('authorization', (string) $this->client->getResponse()->headers->get('Access-Control-Allow-Headers'));
    }

    public function testResponsesExposeTheLocationHeader(): void
    {
        $this->client->request('GET', '/books', server: ['HTTP_ORIGIN' => 'https://127.0.0.1']);

        self::assertResponseHeaderSame('Access-Control-Allow-Origin', 'https://127.0.0.1');
        self::assertStringContainsStringIgnoringCase('Location', (string) $this->client->getResponse()->headers->get('Access-Control-Expose-Headers'));
    }

    public function testOtherOriginsAreNotAllowed(): void
    {
        $this->client->request('OPTIONS', '/books', server: [
            'HTTP_ORIGIN' => 'https://evil.example',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
        ]);

        self::assertResponseNotHasHeader('Access-Control-Allow-Origin');
    }
}
