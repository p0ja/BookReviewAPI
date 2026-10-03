<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * PATCH /reviews/{id}: every field is optional, a missing one keeps its value. The
 * rules for a given field are those of CreateReview.
 */
class UpdateReview
{
    public function __construct(
        #[Assert\Length(max: 255, normalizer: 'trim')]
        public readonly ?string $name = null,
        #[Assert\Length(min: 3, max: 10000, normalizer: 'trim')]
        public readonly ?string $content = null,
        #[Assert\Regex('/^[0-5]$/', message: 'Rating must be a whole number from 0 to 5.')]
        public readonly ?string $rating = null,
    ) {
    }
}
