<?php

declare(strict_types=1);

namespace App\Http\Requests\Leader\Trader;

use App\Models\User;
use App\Support\TeamLeaderTraderCommissionResolver;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCommissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'commission' => [
                'required',
                'numeric',
                'min:0',
                'max:100',
                function (string $attribute, mixed $value, \Closure $fail) {
                    $teamLeader = auth()->user();
                    if (! $teamLeader instanceof User) {
                        $fail(__('Не удалось определить тимлида.'));
                        return;
                    }

                    if (! TeamLeaderTraderCommissionResolver::isFlexibleEnabled($teamLeader)) {
                        $fail(__('Гибкая комиссия для ваших трейдеров отключена.'));
                        return;
                    }

                    $min = $teamLeader->team_leader_flexible_trader_commission_min;
                    $max = $teamLeader->team_leader_flexible_trader_commission_max;

                    if ($min === null || $max === null) {
                        $fail(__('Диапазон гибкой комиссии не настроен администратором.'));
                        return;
                    }

                    $commission = (float) $value;
                    if ($commission < (float) $min || $commission > (float) $max) {
                        $fail(__('Комиссия должна быть в диапазоне от :min% до :max%.', [
                            'min' => $min,
                            'max' => $max,
                        ]));
                    }
                },
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'commission' => __('комиссия'),
        ];
    }
}
