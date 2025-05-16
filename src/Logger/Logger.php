<?php

declare(strict_types=1);

namespace App\Logger;

use Psr\Log\LogLevel;
use Monolog\Logger as MonologLogger;

/**
 * @method emergency(string $namespace, string $message, array $context = []): void
 * @method alert(string $namespace, string $message, array $context = []): void
 * @method critical(string $namespace, string $message, array $context = []): void
 * @method error(string $namespace, string $message, array $context = []): void
 * @method warning(string $namespace, string $message, array $context = []): void
 * @method notice(string $namespace, string $message, array $context = []): void
 * @method info(string $namespace, string $message, array $context = []): void
 * @method debug(string $namespace, string $message, array $context = []): void
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
        private readonly MonologLogger $logger
    ) {
    }

    public function log(string $namespace, string $message, array $context = [], mixed $level = null): void
    {
        $this->logger->log(
            LogLevel::INFO,
            $message,
            array_merge(
                $context,
                [
                    'namespace' => $namespace,
                    'level' => $level,
                ]
            )
        );
    }

    public function __call(string $name, array $arguments): void
    {
        $name = strtolower((string) $name);

        if (!method_exists($this->logger, $name)) {
            throw new \BadMethodCallException("Method $name() does not exist");
        }

        [$namespace, $message, $context] = $arguments;
        $context = $context ?? [];

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
