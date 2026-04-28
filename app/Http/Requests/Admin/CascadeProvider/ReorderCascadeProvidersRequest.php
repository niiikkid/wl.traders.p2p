<?php

namespace App\Http\Requests\Admin\CascadeProvider;

use App\Models\CascadeProvider;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ReorderCascadeProvidersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'distinct'],
            'ids.*' => ['integer', Rule::exists('cascade_providers', 'id')],
        ];
    }

    public function attributes(): array
    {
        return [
            'ids' => __('идентификаторы провайдеров'),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            /** @var list<int> $ids */
            $ids = array_values(array_map('intval', $this->input('ids', [])));

            sort($ids);

            $expected = CascadeProvider::query()
                ->orderBy('id')
                ->pluck('id')
                ->map(fn (mixed $id): int => (int) $id)
                ->values()
                ->all();

            if ($ids !== $expected) {
                $validator->errors()->add(
                    'ids',
                    __('Нужно передать полный список провайдеров без пропусков и дубликатов.'),
                );
            }
        });
    }
}
