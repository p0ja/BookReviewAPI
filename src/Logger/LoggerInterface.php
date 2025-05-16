<?php

declare(strict_types=1);

namespace App\Logger;

interface LoggerInterface
{
    /**
     * @param string $namespace
     * @param string $message
     * @param array $context
     * @param mixed $level
     * @return void
     */
    public function log(string $namespace, string $message, array $context = [], mixed $level = null): void;
}
