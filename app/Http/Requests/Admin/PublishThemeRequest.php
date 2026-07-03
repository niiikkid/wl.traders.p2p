<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class PublishThemeRequest extends FormRequest
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
            'type' => ['required', 'string', 'in:builtin,custom'],
            'slug' => ['required', 'string', 'max:60'],
            'name' => ['required', 'string', 'max:60'],
            'colorScheme' => ['required', 'string', 'in:light,dark'],
            'tokens' => ['required', 'array'],
            'tokens.*' => ['nullable', 'string', 'max:120'],
        ];
    }

    /**
     * Build a sanitized, storage-safe theme payload.
     *
     * The tokens end up inlined into a `<style>` block for every visitor, so we
     * strictly whitelist token names and strip any CSS-breaking characters from
     * their values.
     *
     * @return array{type:string,slug:string,name:string,colorScheme:string,tokens:array<string,string>}
     */
    public function toThemePayload(): array
    {
        $tokens = [];

        foreach ((array) $this->input('tokens', []) as $key => $value) {
            if (! is_string($key) || ! preg_match('/^--[a-z0-9-]+$/', $key)) {
                continue;
            }

            if (! is_string($value) || $value === '') {
                continue;
            }

            $tokens[$key] = trim(preg_replace('/[;{}<>]/', '', $value));
        }

        return [
            'type' => $this->string('type')->value(),
            'slug' => $this->string('slug')->value(),
            'name' => $this->string('name')->value(),
            'colorScheme' => $this->string('colorScheme')->value() === 'dark' ? 'dark' : 'light',
            'tokens' => $tokens,
        ];
    }
}
