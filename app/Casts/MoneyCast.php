<?php

namespace App\Casts;

use App\Services\Money\Money;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class MoneyCast implements CastsAttributes
{
    /**
     * Cast the given value.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value === null) {
            return null;
        }

        if (! empty($attributes[$key.'_currency'])) {
            $currency_field = $key.'_currency';
        } elseif (! empty($attributes['currency'])) {
            $currency_field = 'currency';
        } else {
            throw new \Exception('Currency field is empty.');
        }

        if ($value === null) {
            return null;
        }

        return Money::fromUnits($value, $attributes[$currency_field]);
    }

    /**
     * Prepare the given value for storage.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value instanceof Money) {
            return $value->toUnits();
        }

        return $value;
    }
}
