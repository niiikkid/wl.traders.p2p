<?php

namespace App\Models\ValueObjects\Settings;

class CurrencyPriceParserSettings
{
    public function __construct(
        public CurrencyPriceParserSideSettings $buy,
        public CurrencyPriceParserSideSettings $sell,
    ) {}

    public static function fromArray(?array $data): self
    {
        $data = $data ?? [];

        if (isset($data['buy']) || isset($data['sell'])) {
            $buyData = $data['buy'] ?? [];
            $sellData = $data['sell'] ?? [];
        } else {
            // старый формат — одинаковые настройки для обеих сторон
            $buyData = $sellData = $data;
        }

        return new self(
            buy: CurrencyPriceParserSideSettings::fromArray($buyData),
            sell: CurrencyPriceParserSideSettings::fromArray($sellData),
        );
    }

    public static function defaults(): self
    {
        return new self(
            buy: CurrencyPriceParserSideSettings::defaults(),
            sell: CurrencyPriceParserSideSettings::defaults(),
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
