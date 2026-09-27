<?php

declare(strict_types=1);

namespace App\Logger;

use Monolog\Logger as MonologLogger;
use Psr\Log\LogLevel;

/**
 * @method void emergency(string $namespace, string $message, array<string, mixed> $context = [])
 * @method void alert(string $namespace, string $message, array<string, mixed> $context = [])
 * @method void critical(string $namespace, string $message, array<string, mixed> $context = [])
 * @method void error(string $namespace, string $message, array<string, mixed> $context = [])
 * @method void warning(string $namespace, string $message, array<string, mixed> $context = [])
 * @method void notice(string $namespace, string $message, array<string, mixed> $context = [])
 * @method void info(string $namespace, string $message, array<string, mixed> $context = [])
 * @method void debug(string $namespace, string $message, array<string, mixed> $context = [])
 */
class Logger implements LoggerInterface
{
    private const LOG_LEVELS = [
        LogLevel::EMERGENCY,
        LogLevel::ALERT,
        LogLevel::CRITICAL,
        LogLevel::ERROR,
        LogLevel::WARNING,
        LogLevel::NOTICE,
        LogLevel::INFO,
        LogLevel::DEBUG,
    ];

    public function __construct(
        private readonly MonologLogger $logger,
    ) {
    }

    /**
     * @param array<string, mixed> $context
     */
    public function log(string $namespace, string $message, array $context = [], mixed $level = null): void
    {
        $this->logger->log(
            is_string($level) && in_array(strtolower($level), self::LOG_LEVELS, true) ? strtolower($level) : LogLevel::INFO,
            $message,
            array_merge(
                $context,
                [
                    'namespace' => $namespace,
                ]
            )
        );
    }

    /**
     * @param array{0: string, 1: string, 2?: array<string, mixed>} $arguments namespace, message, context
     */
    public function __call(string $name, array $arguments): void
    {
        $name = strtolower((string) $name);

        if (!method_exists($this->logger, $name)) {
            throw new \BadMethodCallException("Method $name() does not exist");
        }

        [$namespace, $message] = $arguments;
        $context = $arguments[2] ?? [];

        $this->logger->log(
            in_array($name, self::LOG_LEVELS, true) ? $name : LogLevel::INFO,
            $message,
            array_merge(
                $context,
                [
                    'namespace' => $namespace,
                ]
            )
        );
    }
}
