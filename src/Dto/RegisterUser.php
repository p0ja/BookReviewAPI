<?php

declare(strict_types=1);

namespace App\Dto;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

// The fields are nullable so a missing one reaches the validator; the docs list the
// ones a request must have, which may not be null.
#[OA\Schema(required: ['email', 'password'])]
class RegisterUser
{
    public function __construct(
        // Nullable so a missing field gets CreateBook::REQUIRED, not a type error.
        #[Assert\NotNull(message: CreateBook::REQUIRED)]
        #[Assert\NotBlank(allowNull: true, normalizer: 'trim')]
        #[Assert\Email(normalizer: 'trim')]
        #[Assert\Length(max: 180, normalizer: 'trim')]
        #[OA\Property(nullable: false)]
        public readonly ?string $email = null,
        // The upper bound keeps hashing a huge password from tying up the server.
        #[Assert\NotNull(message: CreateBook::REQUIRED)]
        #[Assert\NotBlank(allowNull: true)]
        #[Assert\Length(min: 8, max: 4096)]
        #[OA\Property(nullable: false)]
        public readonly ?string $password = null,
    ) {
    }
}
