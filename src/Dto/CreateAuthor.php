<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class CreateAuthor
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Type('string')]
        #[Assert\Length(max: 255)]
        public readonly string $name,
        #[Assert\Type('string')]
        #[Assert\Length(max: 255)]
        public readonly ?string $info = null,
    ) {
    }
}
