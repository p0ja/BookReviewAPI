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

    public function testLogUsesTheRequestedLevelAndAddsTheNamespaceToTheContext(): void
    {
        $this->logger->log('book_restApi', 'Book not found', ['id' => 7], LogLevel::ERROR);

        $record = $this->handler->getRecords()[0];
        self::assertSame(Level::Error, $record->level);
        self::assertSame('Book not found', $record->message);
        self::assertSame(['id' => 7, 'namespace' => 'book_restApi'], $record->context);
    }

    public function testLogWithoutALevelLogsAtInfo(): void
    {
        $this->logger->log('book_restApi', 'Book not found');

        self::assertSame(Level::Info, $this->handler->getRecords()[0]->level);
    }

    public function testLogWithAnUnknownLevelLogsAtInfo(): void
    {
        $this->logger->log('book_restApi', 'Book not found', [], 'loud');

        self::assertSame(Level::Info, $this->handler->getRecords()[0]->level);
    }

    public function testLevelMethodsDoNotRequireAContext(): void
    {
        $this->logger->error('book_restApi', 'Broken');

        self::assertTrue($this->handler->hasErrorThatContains('Broken'));
        self::assertSame(['namespace' => 'book_restApi'], $this->handler->getRecords()[0]->context);
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

        // Deliberately not a log level, to check that __call() rejects it.
        $this->logger->shout('book_restApi', 'Hello', []); // @phpstan-ignore method.notFound
    }
}
