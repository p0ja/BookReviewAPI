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
        #[Assert\NotBlank(allowNull: true)]
        #[Assert\Length(max: 255)]
        public readonly ?string $title = null,
        #[Assert\NotBlank(allowNull: true)]
        #[Assert\Length(min: 10, max: 13)]
        public readonly ?string $isbn = null,
        #[Assert\Length(max: 255)]
        public readonly ?string $description = null,
        // GreaterThan alone compares a non-numeric string as text ("abc" > "0"), so check the format first.
        #[Assert\Regex('/^-?\d+(\.\d+)?$/', message: 'Price must be a decimal number, e.g. 29.99.')]
        #[Assert\GreaterThan(0)]
        public readonly ?string $price = null,
        #[Assert\Length(max: 255)]
        public readonly ?string $genre = null,
        #[Assert\Length(max: 25)]
        public readonly ?string $publish_date = null,
        /** @var list<CreateAuthor>|null replaces all the authors when given */
        #[Assert\Valid]
        public ?array $authors = null,
    ) {
    }
}
