<?php

declare(strict_types=1);

namespace App\Dto;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

// The fields are nullable so a missing one reaches the validator; the docs list the
// ones a request must have, which may not be null.
#[OA\Schema(required: ['name'])]
class CreateAuthor
{
    public function __construct(
        // Stored and matched trimmed (AuthorRepository::findOrCreate()), so checked trimmed.
        // Nullable so a missing name gets CreateBook::REQUIRED, not a type error.
        #[Assert\NotNull(message: CreateBook::REQUIRED)]
        #[Assert\NotBlank(allowNull: true, normalizer: 'trim')]
        #[Assert\Type('string')]
        #[Assert\Length(max: 255, normalizer: 'trim')]
        #[OA\Property(nullable: false)]
        public readonly ?string $name = null,
        #[Assert\Type('string')]
        #[Assert\Length(max: 255)]
        public readonly ?string $info = null,
    ) {
    }
}
