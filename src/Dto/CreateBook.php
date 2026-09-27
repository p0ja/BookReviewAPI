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
        #[Assert\Length(max: 10000)]
        public readonly string $description,
        // In sequence: the comparisons treat a non-numeric string as text ("abc" > "0"), so
        // they only run once the format is right. decimal(10,2) holds at most 99999999.99.
        #[Assert\Sequentially([
            new Assert\Regex('/^-?\d+(\.\d+)?$/', message: 'Price must be a decimal number, e.g. 29.99.'),
            new Assert\GreaterThan(0),
            new Assert\LessThanOrEqual(99999999.99),
        ])]
        public readonly string $price,
        #[Assert\Type('string')]
        #[Assert\Length(max: 255)]
        public readonly string $genre,
        #[Assert\Date(message: 'Publish date must be a date in the form YYYY-MM-DD.')]
        public readonly string $publish_date,
        /** @var list<CreateAuthor>|null */
        #[Assert\Type('array')]
        #[Assert\Valid]
        public ?array $authors = null,
    ) {
    }
}
