<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class CreateReview
{
    public function __construct(
        // The values are stored trimmed, so they are checked trimmed.
        #[Assert\Type('string')]
        #[Assert\Length(max: 255, normalizer: 'trim')]
        public readonly string $name,
        #[Assert\Type('string')]
        #[Assert\Length(min: 3, max: 10000, normalizer: 'trim')]
        public readonly string $content,
        #[Assert\NotBlank]
        #[Assert\Type('string')]
        #[Assert\Regex('/^[0-5]$/', message: 'Rating must be a whole number from 0 to 5.')]
        public readonly string $rating,
    ) {
    }
}
