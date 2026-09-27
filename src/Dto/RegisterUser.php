<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class RegisterUser
{
    public function __construct(
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Email(normalizer: 'trim')]
        #[Assert\Length(max: 180, normalizer: 'trim')]
        public readonly string $email,
        // The upper bound keeps hashing a huge password from tying up the server.
        #[Assert\NotBlank]
        #[Assert\Length(min: 8, max: 4096)]
        public readonly string $password,
    ) {
    }
}
