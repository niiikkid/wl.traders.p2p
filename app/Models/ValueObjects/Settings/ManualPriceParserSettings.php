<?php

declare(strict_types=1);

namespace App\Models\ValueObjects\Settings;

class ManualPriceParserSettings
{
    public function __construct(
        public ManualPriceParserSideSettings $buy,
        public ManualPriceParserSideSettings $sell,
    ) {}

    public static function fromArray(?array $data, ?string $currencyCode = null): self
    {
        $data = $data ?? [];

        if (isset($data['buy']) || isset($data['sell'])) {
            $buyData = $data['buy'] ?? [];
            $sellData = $data['sell'] ?? [];
        } else {
            $buyData = $sellData = $data;
        }

        $defaults = self::defaults($currencyCode);

        return new self(
            buy: ManualPriceParserSideSettings::fromArray($buyData + $defaults->buy->toArray()),
            sell: ManualPriceParserSideSettings::fromArray($sellData + $defaults->sell->toArray()),
        );
    }

    public static function defaults(?string $currencyCode = null): self
    {
        $rate = $currencyCode === null ? 0.0 : (float) config('money.currencies.'.strtoupper($currencyCode).'.manual_rate', 0.0);

        return new self(
            buy: new ManualPriceParserSideSettings(rate: $rate),
            sell: new ManualPriceParserSideSettings(rate: $rate),
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
