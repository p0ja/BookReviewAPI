<?php

declare(strict_types=1);

namespace App\Dto;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

// The fields are nullable so a missing one reaches the validator; the docs list the
// ones a request must have, which may not be null.
#[OA\Schema(required: ['name', 'content', 'rating'])]
class CreateReview
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
        #[Assert\Type('string')]
        #[Assert\Length(max: 255, normalizer: 'trim')]
        #[OA\Property(nullable: false)]
        public readonly ?string $name = null,
        #[Assert\NotNull(message: self::REQUIRED)]
        #[Assert\Type('string')]
        #[Assert\Length(min: 3, max: 10000, normalizer: 'trim')]
        #[OA\Property(nullable: false)]
        public readonly ?string $content = null,
        #[Assert\NotNull(message: self::REQUIRED)]
        #[Assert\NotBlank(allowNull: true)]
        #[Assert\Type('string')]
        #[Assert\Regex('/^[0-5]$/', message: 'Rating must be a whole number from 0 to 5.')]
        #[OA\Property(nullable: false)]
        public readonly ?string $rating = null,
    ) {
    }
}
