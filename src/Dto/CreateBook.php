<?php

declare(strict_types=1);

namespace App\Dto;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

// The fields are nullable so a missing one reaches the validator; the docs list the
// ones a request must have, which may not be null.
#[OA\Schema(required: ['title', 'isbn', 'description', 'price', 'genre', 'publish_date'])]
class CreateBook
{
    /**
     * A missing or null field: nullable with a default, so it reaches the validator and
     * gets this message rather than the serializer's "should be of type string", which
     * also hides every other violation of the request.
     */
    public const REQUIRED = 'This field is required.';

    public function __construct(
        // The values are stored trimmed, so they are checked trimmed.
        #[Assert\NotNull(message: self::REQUIRED)]
        #[Assert\NotBlank(allowNull: true, normalizer: 'trim')]
        #[Assert\Type('string')]
        #[Assert\Length(max: 255, normalizer: 'trim')]
        #[OA\Property(nullable: false)]
        public readonly ?string $title = null,
        #[Assert\NotNull(message: self::REQUIRED)]
        #[Assert\NotBlank(allowNull: true, normalizer: 'trim')]
        #[Assert\Type('string')]
        #[Assert\Length(min: 10, max: 13, normalizer: 'trim')]
        #[OA\Property(nullable: false)]
        public readonly ?string $isbn = null,
        #[Assert\NotNull(message: self::REQUIRED)]
        #[Assert\Type('string')]
        #[Assert\Length(max: 10000)]
        #[OA\Property(nullable: false)]
        public readonly ?string $description = null,
        // In sequence: the comparisons treat a non-numeric string as text ("abc" > "0"), so
        // they only run once the format is right. decimal(10,2) holds at most 99999999.99.
        #[Assert\NotNull(message: self::REQUIRED)]
        #[Assert\Sequentially([
            new Assert\Regex('/^-?\d+(\.\d+)?$/', message: 'Price must be a decimal number, e.g. 29.99.'),
            new Assert\GreaterThan(0),
            new Assert\LessThanOrEqual(99999999.99),
        ])]
        #[OA\Property(nullable: false)]
        public readonly ?string $price = null,
        #[Assert\NotNull(message: self::REQUIRED)]
        #[Assert\Type('string')]
        #[Assert\Length(max: 255, normalizer: 'trim')]
        #[OA\Property(nullable: false)]
        public readonly ?string $genre = null,
        // Required, but "" clears the date.
        #[Assert\NotNull(message: self::REQUIRED)]
        #[Assert\Date(message: 'Publish date must be a date in the form YYYY-MM-DD.')]
        #[OA\Property(nullable: false)]
        public readonly ?string $publish_date = null,
        /** @var list<CreateAuthor>|null */
        #[Assert\Type('array')]
        #[Assert\Valid]
        public ?array $authors = null,
    ) {
    }
}
