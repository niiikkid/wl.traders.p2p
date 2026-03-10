<?php

declare(strict_types=1);

namespace App\Models\ValueObjects\Settings;

class ManualPriceParserSettings
{
    public function __construct(
        public ManualPriceParserSideSettings $buy,
        public ManualPriceParserSideSettings $sell,
    ) {}

    public static function fromArray(?array $data): self
    {
        $data = $data ?? [];

        if (isset($data['buy']) || isset($data['sell'])) {
            $buyData = $data['buy'] ?? [];
            $sellData = $data['sell'] ?? [];
        } else {
            $buyData = $sellData = $data;
        }

        return new self(
            buy: ManualPriceParserSideSettings::fromArray($buyData),
            sell: ManualPriceParserSideSettings::fromArray($sellData),
        );
    }

    public static function defaults(): self
    {
        return new self(
            buy: ManualPriceParserSideSettings::defaults(),
            sell: ManualPriceParserSideSettings::defaults(),
        );
    }

    public function toArray(): array
    {
        return [
            'buy' => $this->buy->toArray(),
            'sell' => $this->sell->toArray(),
        ];
    }
}
