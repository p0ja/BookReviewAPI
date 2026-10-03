<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * PATCH /authors/{id} (admins): every field is optional, a missing one keeps its
 * value; an empty info clears it.
 */
class UpdateAuthor
{
    public function __construct(
        // Stored and matched trimmed, so checked trimmed.
        #[Assert\NotBlank(allowNull: true, normalizer: 'trim')]
        #[Assert\Length(max: 255, normalizer: 'trim')]
        public readonly ?string $name = null,
        #[Assert\Length(max: 255)]
        public readonly ?string $info = null,
    ) {
    }
}
