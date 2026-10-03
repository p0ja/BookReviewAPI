<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * PATCH /books/{id}: every field is optional, a missing one keeps its value. The
 * rules for a given field are those of CreateBook.
 */
class UpdateBook
{
    public function __construct(
        #[Assert\NotBlank(allowNull: true, normalizer: 'trim')]
        #[Assert\Length(max: 255, normalizer: 'trim')]
        public readonly ?string $title = null,
        #[Assert\NotBlank(allowNull: true, normalizer: 'trim')]
        #[Assert\Length(min: 10, max: 13, normalizer: 'trim')]
        public readonly ?string $isbn = null,
        #[Assert\Length(max: 10000)]
        public readonly ?string $description = null,
        // In sequence: the comparisons treat a non-numeric string as text ("abc" > "0"), so
        // they only run once the format is right. decimal(10,2) holds at most 99999999.99.
        #[Assert\Sequentially([
            new Assert\Regex('/^-?\d+(\.\d+)?$/', message: 'Price must be a decimal number, e.g. 29.99.'),
            new Assert\GreaterThan(0),
            new Assert\LessThanOrEqual(99999999.99),
        ])]
        public readonly ?string $price = null,
        #[Assert\Length(max: 255, normalizer: 'trim')]
        public readonly ?string $genre = null,
        #[Assert\Date(message: 'Publish date must be a date in the form YYYY-MM-DD.')]
        public readonly ?string $publish_date = null,
        /** @var list<CreateAuthor>|null replaces all the authors when given */
        #[Assert\Valid]
        public ?array $authors = null,
    ) {
    }
}
