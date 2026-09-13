<?php

declare(strict_types=1);

namespace App\Tests\Logger;

use App\Logger\Logger;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\Logger as MonologLogger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;

class LoggerTest extends TestCase
{
    private TestHandler $handler;
    private Logger $logger;

    protected function setUp(): void
    {
        $this->handler = new TestHandler();
        $this->logger = new Logger(new MonologLogger('test', [$this->handler]));
    }

    public function testLogAddsTheNamespaceAndLevelToTheContext(): void
    {
        $this->logger->log('book_restApi', 'Book not found', ['id' => 7], LogLevel::ERROR);

        $record = $this->handler->getRecords()[0];
        self::assertSame('Book not found', $record->message);
        self::assertSame(['id' => 7, 'namespace' => 'book_restApi', 'level' => LogLevel::ERROR], $record->context);
    }

    public function testLevelMethodsLogAtThatLevel(): void
    {
        $this->logger->warning('review_restApi', 'Slow query', ['ms' => 900]);

        self::assertTrue($this->handler->hasRecordThatPasses(
            static fn ($record): bool => 'Slow query' === $record->message
                && ['ms' => 900, 'namespace' => 'review_restApi'] === $record->context,
            Level::Warning,
        ));
    }

    public function testUnknownMethodThrows(): void
    {
        $this->expectException(\BadMethodCallException::class);

        $this->logger->shout('book_restApi', 'Hello', []);
    }
}
