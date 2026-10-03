<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

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
        public readonly ?string $name = null,
        #[Assert\NotNull(message: self::REQUIRED)]
        #[Assert\Type('string')]
        #[Assert\Length(min: 3, max: 10000, normalizer: 'trim')]
        public readonly ?string $content = null,
        #[Assert\NotNull(message: self::REQUIRED)]
        #[Assert\NotBlank(allowNull: true)]
        #[Assert\Type('string')]
        #[Assert\Regex('/^[0-5]$/', message: 'Rating must be a whole number from 0 to 5.')]
        public readonly ?string $rating = null,
    ) {
    }
}
