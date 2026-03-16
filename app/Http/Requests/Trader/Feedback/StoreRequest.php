<?php

namespace App\Http\Requests\Trader\Feedback;

use App\Models\Feedback;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreRequest extends FormRequest
{
    public const COOLDOWN_SECONDS = 300;

    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'content.required' => 'Введите текст обратной связи.',
            'content.max' => 'Максимальная длина сообщения — 1000 символов.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $user = $this->user();

            if ($user === null) {
                return;
            }

            $latestFeedback = Feedback::query()
                ->where('user_id', $user->id)
                ->latest('created_at')
                ->first(['created_at']);

            if ($latestFeedback === null || $latestFeedback->created_at === null) {
                return;
            }

            $cooldownEndsAt = $latestFeedback->created_at->copy()->addSeconds(self::COOLDOWN_SECONDS);

            if (! $cooldownEndsAt->isFuture()) {
                return;
            }

            $remainingSeconds = now()->diffInSeconds($cooldownEndsAt);
            $remainingMinutes = intdiv($remainingSeconds, 60);
            $remainingTailSeconds = $remainingSeconds % 60;

            $validator->errors()->add(
                'content',
                sprintf(
                    'Следующее сообщение можно отправить через %02d:%02d.',
                    $remainingMinutes,
                    $remainingTailSeconds
                )
            );
        });
    }
}
