<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class CreateBook
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Type('string')]
        #[Assert\Length(max: 255)]
        public readonly string $title,
        #[Assert\NotBlank]
        #[Assert\Type('string')]
        #[Assert\Length(min: 10, max: 13)]
        public readonly string $isbn,
        #[Assert\Type('string')]
        #[Assert\Length(max: 255)]
        public readonly string $description,
        #[Assert\GreaterThan(0)]
        public readonly string $price,
        #[Assert\Type('string')]
        #[Assert\Length(max: 255)]
        public readonly string $genre,
        #[Assert\Type('string')]
        #[Assert\Length(max: 25)]
        public readonly string $publish_date,
        #[Assert\Type('array')]
        public ?array $authors = null,
    ) {
    }
}
