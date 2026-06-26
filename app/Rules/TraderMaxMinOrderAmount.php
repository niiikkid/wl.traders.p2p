<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class TraderMaxMinOrderAmount implements ValidationRule
{
    public function __construct(
        private readonly ?User $trader,
        private readonly ?User $actor,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if ($this->actor?->hasRole('Super Admin')) {
            return;
        }

        $limit = $this->trader?->effectiveMaxMinOrderAmount();
        if ($limit === null) {
            return;
        }

        if ((int) $value > $limit) {
            $fail("Минимальная сумма сделки не может превышать {$limit}. Для увеличения обратитесь в поддержку.");
        }
    }
}
