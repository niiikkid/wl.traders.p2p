<?php

namespace App\Services\Money;

use App\Services\Money\Utils\FormatMoney;
use Illuminate\Contracts\Support\Arrayable;

class Money implements Arrayable
{
    const DEFAULT_PRECISION = 50;

    public function __construct(
        private readonly string   $amount, //in units
        private readonly Currency $currency
    )
    {}

    public static function fromPrecision(string $amount, string $currency): static
    {
        //TODO amount validation

        $currency = new Currency($currency);

        $amount = FormatMoney::precisionToUnits($amount, $currency->getPrecision());

        return new static($amount, $currency);
    }

    public static function fromUnits(string $amount, string $currency): static
    {
        //TODO amount validation

        $currency = new Currency($currency);

        return new static($amount, $currency);
    }

    public static function zero(string $currency): static
    {
        //TODO amount validation

        $currency = new Currency($currency);

        return new static(0, $currency);
    }

    //100.50
    public function toPrecision(): string
    {
        return FormatMoney::unitsToPrecision($this->amount, $this->currency->getPrecision());
    }

    //10050
    public function toUnits(): string
    {
        return $this->amount;
    }

    public function toUnitsInt(): int
    {
        return (int)$this->amount;
    }

    //100.5
    public function toBeauty(): string
    {
        $amount = $this->toPrecision();

        if (str_contains($amount, '.')) {
            $display_precision = max(0, $this->currency->getDisplayPrecision());

            [$integer, $fraction] = explode('.', $amount, 2);

            if (strlen($fraction) > $display_precision) {
                $amount = $display_precision === 0
                    ? $integer
                    : $integer.'.'.substr($fraction, 0, $display_precision);
            }
        }

        return FormatMoney::beautifyPrecision($amount);
    }

    public function toInt(): int
    {
        return (int)$this->toPrecision();
    }

    public function getCurrency(): Currency
    {
        return $this->currency;
    }

    public function add(string | Money $amount): self
    {
        if ($amount instanceof Money) {
            $amount = $amount->toPrecision();
        }

        return self::fromPrecision(
            amount: bcadd($this->toPrecision(), $amount, 50),
            currency: $this->currency
        );
    }

    public function sub(string | Money $amount): self
    {
        if ($amount instanceof Money) {
            $amount = $amount->toPrecision();
        }

        return self::fromPrecision(
            amount: bcsub($this->toPrecision(), $amount, 50),
            currency: $this->currency
        );
    }

    public function mul(string | Money $amount): self
    {
        if ($amount instanceof Money) {
            $amount = $amount->toPrecision();
        }

        return self::fromPrecision(
            amount: bcmul($this->toPrecision(), $amount, 50),
            currency: $this->currency
        );
    }

    public function div(string | Money $amount): self
    {
        if ($amount instanceof Money) {
            $amount = $amount->toPrecision();
        }

        return self::fromPrecision(
            amount: bcdiv($this->toPrecision(), $amount, 50),
            currency: $this->currency
        );
    }

    public function abs(): self
    {
        return self::fromPrecision(
            amount: abs($this->toPrecision()),
            currency: $this->currency
        );
    }

    public function convert(Money $conversion_amount, Currency $currency): Money
    {
        if ($this->getCurrency()->getCode() !== $conversion_amount->getCurrency()->getCode()) {
            throw new \Exception('Currencies must be equal');
        }

        return Money::fromUnits(
            amount: $this->div($conversion_amount),
            currency: $currency,
        );
    }

    public function convertBack(Money $conversion_amount, Currency $currency): Money
    {
        if ($this->getCurrency()->getCode() === $conversion_amount->getCurrency()->getCode()) {
            throw new \Exception('Currencies must not be equal');
        }

        return Money::fromUnits(
            amount: $this->mul($conversion_amount),
            currency: $currency,
        );
    }

    public function greaterThan(Money $amount): bool
    {
        $this->throwIfCurrencyNotEqualsToBase($amount);

        return bccomp($this->toPrecision(), $amount->toPrecision(), self::DEFAULT_PRECISION) === 1;
    }

    public function lessThan(Money $amount): bool
    {
        $this->throwIfCurrencyNotEqualsToBase($amount);

        return bccomp($this->toPrecision(), $amount->toPrecision(), self::DEFAULT_PRECISION) === -1;
    }

    public function equals(Money $amount): bool
    {
        $this->throwIfCurrencyNotEqualsToBase($amount);

        return bccomp($this->toPrecision(), $amount->toPrecision(), self::DEFAULT_PRECISION) === 0;
    }

    public function greaterOrEquals(Money $amount): bool
    {
        $this->throwIfCurrencyNotEqualsToBase($amount);

        return $this->greaterThan($amount) || $this->equals($amount);
    }

    public function lessOrEquals(Money $amount): bool
    {
        $this->throwIfCurrencyNotEqualsToBase($amount);

        return $this->lessThan($amount) || $this->equals($amount);
    }

    public function greaterThanZero(): bool
    {
        $amount = Money::fromPrecision(0, $this->currency);

        return $this->greaterThan($amount);
    }

    public function lessThanZero(): bool
    {
        $amount = Money::fromPrecision(0, $this->currency);

        return $this->lessThan($amount);
    }

    public function equalsToZero(): bool
    {
        $amount = Money::fromPrecision(0, $this->currency);

        return $this->equals($amount);
    }

    protected function throwIfCurrencyNotEqualsToBase(Money $amount): void
    {
        if (! $this->currencyEqualsToBase($amount)) {
            throw new \Exception('Currencies must be equal.');
        }
    }

    protected function currencyEqualsToBase(Money $amount): bool
    {
        return $this->getCurrency()->getCode() === $amount->getCurrency()->getCode();
    }

    public function __toString(): string
    {
        return $this->toUnits();
    }

    public function toArray(): array
    {
        return [
            'amount' => $this->toUnits(),
            'currency' => $this->getCurrency()->getCode(),
        ];
    }
}
