<?php

declare(strict_types=1);

namespace App\Logger;

interface LoggerInterface
{
    public function log(string $namespace, string $message, array $context = [], mixed $level = null): void;
}
