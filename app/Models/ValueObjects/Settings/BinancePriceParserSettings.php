<?php

namespace App\Models\ValueObjects\Settings;

class BinancePriceParserSettings
{
    public function __construct(
        public BinancePriceParserSideSettings $buy,
        public BinancePriceParserSideSettings $sell,
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
            buy: BinancePriceParserSideSettings::fromArray($buyData),
            sell: BinancePriceParserSideSettings::fromArray($sellData),
        );
    }

    public static function defaults(): self
    {
        return new self(
            buy: BinancePriceParserSideSettings::defaults(),
            sell: BinancePriceParserSideSettings::defaults(),
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
