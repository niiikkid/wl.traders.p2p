<?php

declare(strict_types=1);

namespace App\Models\ValueObjects\Settings;

class ManualPriceParserSideSettings
{
    public function __construct(
        public float $rate = 0.0,
    ) {}

    public static function fromArray(?array $data): self
    {
        $data = $data ?? [];

        return new self(
            rate: isset($data['rate']) ? max(0, (float) $data['rate']) : 0.0
        );
    }

    public static function defaults(): self
    {
        return new self(rate: 0.0);
    }

    public function toArray(): array
    {
        return [
            'rate' => $this->rate,
        ];
    }
}
