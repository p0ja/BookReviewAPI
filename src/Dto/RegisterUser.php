<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class RegisterUser
{
    public function __construct(
        // Nullable so a missing field gets CreateBook::REQUIRED, not a type error.
        #[Assert\NotNull(message: CreateBook::REQUIRED)]
        #[Assert\NotBlank(allowNull: true, normalizer: 'trim')]
        #[Assert\Email(normalizer: 'trim')]
        #[Assert\Length(max: 180, normalizer: 'trim')]
        public readonly ?string $email = null,
        // The upper bound keeps hashing a huge password from tying up the server.
        #[Assert\NotNull(message: CreateBook::REQUIRED)]
        #[Assert\NotBlank(allowNull: true)]
        #[Assert\Length(min: 8, max: 4096)]
        public readonly ?string $password = null,
    ) {
    }
}
