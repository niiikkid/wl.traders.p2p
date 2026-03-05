<?php

namespace App\Http\Requests\Admin\API\Dispute;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/**
 * @property int $order
 * @property UploadedFile $receipt
 */
class StoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'receipt' => ['required', 'image', 'mimes:jpeg,jpg,png', 'max:2048'],
            'order' => ['required', 'exists:orders,id'],
        ];
    }
}
