<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\PaymentDetailSchedule;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class OwnedPaymentDetailSchedule implements ValidationRule
{
    public function __construct(
        private readonly ?int $user_id = null,
    ) {}

    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $user_id = $this->user_id ?? auth()->id();

        if ($user_id === null) {
            $fail('Не удалось проверить владельца расписания.');

            return;
        }

        $exists = PaymentDetailSchedule::query()
            ->where('id', $value)
            ->where('user_id', $user_id)
            ->exists();

        if (! $exists) {
            $fail('Выбранное расписание не найдено или недоступно.');
        }
    }
}
