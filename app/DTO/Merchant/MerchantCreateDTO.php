<?php

declare(strict_types=1);

namespace App\DTO\Merchant;

use App\DTO\BaseDTO;

readonly class MerchantCreateDTO extends BaseDTO
{
    public function __construct(
        public readonly int $user_id,
        public readonly string $name,
        public readonly ?string $description = null,
        public readonly ?string $project_link = null,
    ) {
    }
}


