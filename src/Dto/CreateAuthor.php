<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class CreateAuthor
{
    public function __construct(
        // Stored and matched trimmed (AuthorRepository::findOrCreate()), so checked trimmed.
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Type('string')]
        #[Assert\Length(max: 255, normalizer: 'trim')]
        public readonly string $name,
        #[Assert\Type('string')]
        #[Assert\Length(max: 255)]
        public readonly ?string $info = null,
    ) {
    }
}
