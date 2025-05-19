<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class CreateReview
{
    public function __construct(
        #[Assert\Type('string')]
        #[Assert\Length(max: 255)]
        public readonly string $name,
        #[Assert\Type('string')]
        #[Assert\Length(min: 3, max: 10000)]
        public readonly string $content,
        #[Assert\NotBlank]
        #[Assert\Type('string')]
        #[Assert\Length(min: 0, max: 5)]
        public readonly string $rating,
    ) {
    }
}
