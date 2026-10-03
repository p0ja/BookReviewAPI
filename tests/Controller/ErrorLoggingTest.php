<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Factory\BookFakeDataFactory;
use App\Factory\BookReviewFakeDataFactory;
use App\Factory\UserFakeDataFactory;
use App\Repository\BookAuthorRepository;
use App\Tests\ApiTestCase;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\LogRecord;

/**
 * Each exception is logged once, by Symfony's ErrorListener: the client errors the API
 * answers on purpose at INFO (config/packages/framework.yaml), everything else as before.
 */
class ErrorLoggingTest extends ApiTestCase
{
    private TestHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->handler = new TestHandler();
        static::getContainer()->get('monolog.logger.request')->pushHandler($this->handler);
    }

    public function testNotFoundIsLoggedOnceAtInfo(): void
    {
        $this->authenticate();

        $this->client->request('GET', '/books/999999');

        self::assertResponseStatusCodeSame(404);
        self::assertSame([Level::Info], $this->exceptionLevels());
    }

    public function testValidationFailureIsLoggedAtInfo(): void
    {
        $this->authenticate();

        $this->requestJson('POST', '/books', ['title' => 'x']);

        self::assertResponseStatusCodeSame(422);
        self::assertSame([Level::Info], $this->exceptionLevels());
    }

    public function testForbiddenIsLoggedAtInfo(): void
    {
        $this->authenticate();
        $review = BookReviewFakeDataFactory::createOne(['book' => BookFakeDataFactory::createOne(), 'user' => UserFakeDataFactory::createOne()]);

        $this->client->request('DELETE', '/reviews/'.$review->getId());

        self::assertResponseStatusCodeSame(403);
        self::assertSame([Level::Info], $this->exceptionLevels());
    }

    /**
     * Anything unexpected still reaches the logs as CRITICAL, Symfony's default.
     */
    public function testAServerErrorIsLoggedAsCritical(): void
    {
        $this->authenticate();
        $bookAuthorRepository = self::createStub(BookAuthorRepository::class);
        $bookAuthorRepository->method('createBookAuthor')->willThrowException(new \RuntimeException('Database failure'));
        static::getContainer()->set(BookAuthorRepository::class, $bookAuthorRepository);

        $this->requestJson('POST', '/books', [
            'title' => 'T', 'isbn' => '9780134494166', 'description' => 'd', 'price' => '1.00',
            'genre' => 'g', 'publish_date' => '2020-01-01', 'authors' => [['name' => 'A']],
        ]);

        self::assertResponseStatusCodeSame(500);
        self::assertSame([Level::Critical], $this->exceptionLevels());
    }

    /**
     * @return list<Level> the levels of the "Uncaught PHP Exception" records
     */
    private function exceptionLevels(): array
    {
        return array_values(array_map(
            static fn (LogRecord $record): Level => $record->level,
            array_filter($this->handler->getRecords(), static fn (LogRecord $record): bool => str_starts_with($record->message, 'Uncaught PHP Exception')),
        ));
    }
}
