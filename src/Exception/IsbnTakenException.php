<?php

declare(strict_types=1);

namespace App\Exception;

final class IsbnTakenException extends \RuntimeException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('A book with this ISBN already exists', 0, $previous);
    }
}
