<?php

declare(strict_types=1);

namespace App\Rules;

use App\Services\PaymentDetail\PaymentDetailScheduleIntervalNormalizer;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\ValidationException;

class PaymentDetailScheduleIntervals implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value)) {
            $fail('Укажите хотя бы один рабочий интервал.');

            return;
        }

        try {
            app(PaymentDetailScheduleIntervalNormalizer::class)->normalize($value);
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $messages) {
                foreach ($messages as $message) {
                    $fail($message);
                }
            }
        }
    }
}
