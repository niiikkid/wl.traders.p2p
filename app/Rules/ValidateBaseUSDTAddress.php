<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidateBaseUSDTAddress implements ValidationRule
{
    public function __construct(
        protected ?string $network
    )
    {}

    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $this->network) {
            return;
        }

        if (! $this->isValidUsdtAddress($value, $this->network)) {
            $fail("Неверный USDT адрес: $value в сети: $this->network.");
        }
    }

    protected function isValidUsdtAddress(string $address, string $network): bool {
        switch (strtolower($network)) {
            case 'bsc':
            case 'arb': // Arbitrum
                // Проверим соответствие формату 0x + 40 HEX-символов
                return (bool)preg_match('/^0x[a-fA-F0-9]{40}$/', $address);

            case 'trx': // Tron
                // Минимальная проверка: адрес часто начинается с 'T', а сам адрес должен быть base58.
                // Для более серьёзной проверки нужен алгоритм base58Check. Здесь упрощённая версия.
                // Проверим, что адрес начинается с T и состоит из 34 символов.
                if (strlen($address) === 34 && strpos($address, 'T') === 0) {
                    // Можно добавить дополнительную проверку base58.
                    // Но в большинстве случаев такой простой проверки бывает достаточно.
                    return true;
                }
                return false;

            default:
                // Неизвестная сеть
                return false;
        }
    }
}
