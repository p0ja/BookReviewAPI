<?php

declare(strict_types=1);

namespace App\Output;

use App\Entity\Author;

class AuthorData
{
    /**
     * @return array{id: ?int, name: ?string, info: ?string}
     */
    public function getOutput(Author $author): array
    {
        return [
            'id' => $author->getId(),
            'name' => $author->getName(),
            'info' => $author->getInfo(),
        ];
    }
}
