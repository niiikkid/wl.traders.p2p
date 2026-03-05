<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Http;

class ValidateTRC20Address implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $address = $value;

        if (strlen($address) !== 34) {
            $fail('Поле :attribute должно содержать 34 символа.');
            return;
        }

        if (substr($address, 0, 1) !== 'T') {
            $fail('Поле :attribute должно начинаться с символа "T".');
            return;
        }

        if (!preg_match('/^[A-Za-z0-9]+$/', $address)) {
            $fail('Поле :attribute содержит недопустимые символы.');
            return;
        }

        // Ключ для кеширования
        $cacheKey = 'tron_address_validation_' . $address;

        // Попытка получить данные из кеша
        $data = cache()->remember($cacheKey, now()->addDays(7), function () use ($address) {
            $response = Http::get("https://api.trongrid.io/v1/accounts/{$address}");

            if ($response->failed()) {
                return null;
            }

            return $response->json();
        });

        if (! isset($data['success']) || $data['success'] !== true) {
            $fail("Адрес не существует в сети Tron.");
        }
    }
}
