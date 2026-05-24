<?php

declare(strict_types=1);

namespace App\DTO\TrafficCategory;

use App\DTO\BaseDTO;

readonly class TrafficCategoryUpsertDTO extends BaseDTO
{
    public function __construct(
        public string $name,
        public string $description,
        public bool $enabled_by_default = false,
    ) {}

    /**
     * @param  array{name: string, description: string, enabled_by_default?: bool}  $data
     */
    public static function makeFromRequest(array $data, bool $enabledByDefault = false): static
    {
        return new static(
            name: (string) $data['name'],
            description: (string) $data['description'],
            enabled_by_default: $enabledByDefault,
        );
    }
}
