<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * Another request wrote the same unique data first (an author created at the same
 * time, say); the client can retry and will then find it.
 */
final class ConcurrentWriteException extends \RuntimeException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('Another request changed the same data at the same time; try again', 0, $previous);
    }
}
