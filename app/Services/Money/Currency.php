<?php

namespace App\Services\Money;

use Illuminate\Support\Collection;

/**
 * @method static Currency RUB()
 * @method static Currency KZT()
 //* @method static Currency UZS()
 * @method static Currency EUR()
 * @method static Currency TJS()
 * @method static Currency KGS()
 * @method static Currency UAH()
 * @method static Currency USD()
 * @method static Currency AZN()
 * @method static Currency TRY()
 * @method static Currency IDR()
 * @method static Currency USDT()
 */
class Currency
{
    private string $currency;

    public function __construct(string $currency)
    {
        $this->currency = strtoupper($currency);

        if (! $this->checkCurrencyExists()) {
            throw new \Exception('Currency not exists');
        }
    }

    public static function make(string $currency): Currency
    {
        return new static($currency);
    }

    public function getPrecision(): int
    {
        return $this->getConfig()['precision'];
    }

    public function getDisplayPrecision(): int
    {
        return $this->getConfig()['display_precision'] ?? $this->getPrecision();
    }

    public function getSymbol(): string
    {
        return $this->getConfig()['symbol'];
    }

    public function getName(): string
    {
        return $this->getConfig()['name'];
    }

    public function getCode(): string
    {
        return strtolower($this->currency);
    }

    /**
     * @return Collection<int, Currency>
     */
    public static function getAll(): Collection
    {
        $currencies_config = config('money.currencies');

        $currencies = collect();
        foreach ($currencies_config as $currency => $config) {
            if (! $config['base']) {
                $currencies->push(new self($currency));
            }
        }

        return $currencies;
    }

    /**
     * @return array<int, string>
     */
    public static function getAllCodes(): array
    {
        $currencies_config = config('money.currencies');

        $currencies = [];
        foreach ($currencies_config as $currency => $config) {
            if (! $config['base']) {
                $currencies[] = strtolower($currency);
            }
        }

        return $currencies;
    }

    public function equals(Currency $currency): string
    {
        return $this->getCode() === $currency->getCode();
    }

    public function notEquals(Currency $currency): string
    {
        return $this->getCode() !== $currency->getCode();
    }

    public static function isCurrency(string $currency): bool
    {
        return (bool)config('money.currencies.'.strtoupper($currency));
    }

    protected function getConfig(): array
    {
        return config('money.currencies.'.$this->currency);
    }

    protected function checkCurrencyExists(): bool
    {
        return (bool)config('money.currencies.'.$this->currency);
    }

    public function __toString(): string
    {
        return $this->getCode();
    }

    public static function __callStatic($method, $parameters)
    {
        return new self($method);
    }
}
