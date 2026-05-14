<?php

namespace App\Services\OpenAi;

use App\Contracts\OpenAiServiceContract;
use App\Models\OpenAiSetting;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenAiService implements OpenAiServiceContract
{
    private const BASE_URL = 'https://api.openai.com/v1';

    public function getSettings(): OpenAiSetting
    {
        return OpenAiSetting::query()->firstOrCreate([]);
    }

    public function updateSettings(?string $apiKey, ?string $selectedModel): OpenAiSetting
    {
        $setting = $this->getSettings();
        $data = [
            'selected_model' => $selectedModel ?: null,
        ];

        if (is_string($apiKey) && $apiKey !== '') {
            $data['api_key'] = $apiKey;
        }

        $setting->update($data);

        return $setting->refresh();
    }

    public function refreshModels(?string $apiKey = null): OpenAiSetting
    {
        $setting = $this->getSettings();
        $token = $apiKey ?: $setting->api_key;

        if (! is_string($token) || $token === '') {
            throw new RuntimeException('API ключ OpenAI не задан.');
        }

        $response = $this->client($token)->get('/models');

        if (! $response->successful()) {
            throw new RuntimeException($response->json('error.message') ?: 'Не удалось получить список моделей OpenAI.');
        }

        $models = collect($response->json('data', []))
            ->pluck('id')
            ->filter(fn (mixed $model): bool => is_string($model) && $model !== '')
            ->sort()
            ->values()
            ->toArray();

        $setting->update([
            'available_models' => $models,
            'models_loaded_at' => now(),
            'selected_model' => in_array($setting->selected_model, $models, true)
                ? $setting->selected_model
                : ($this->preferredModel($models) ?? $setting->selected_model),
        ]);

        return $setting->refresh();
    }

    public function prompt(string $prompt, ?string $systemPrompt = null, ?string $model = null): string
    {
        $setting = $this->getSettings();

        if (! $setting->hasApiKey()) {
            throw new RuntimeException('API ключ OpenAI не задан.');
        }

        $selectedModel = $model ?: $setting->selected_model;

        if (! is_string($selectedModel) || $selectedModel === '') {
            throw new RuntimeException('Модель OpenAI не выбрана.');
        }

        $input = [];

        if (is_string($systemPrompt) && $systemPrompt !== '') {
            $input[] = [
                'role' => 'system',
                'content' => $systemPrompt,
            ];
        }

        $input[] = [
            'role' => 'user',
            'content' => $prompt,
        ];

        $response = $this->client($setting->api_key)->post('/responses', [
            'model' => $selectedModel,
            'input' => $input,
        ]);

        if (! $response->successful()) {
            throw new RuntimeException($response->json('error.message') ?: 'OpenAI API вернул ошибку.');
        }

        return (string) ($response->json('output_text') ?? '');
    }

    private function client(string $apiKey): PendingRequest
    {
        return Http::baseUrl(self::BASE_URL)
            ->acceptJson()
            ->asJson()
            ->withToken($apiKey)
            ->connectTimeout(10)
            ->timeout(30);
    }

    private function preferredModel(array $models): ?string
    {
        return collect($models)->first(fn (string $model): bool => str_starts_with($model, 'gpt-5.5'))
            ?? collect($models)->first(fn (string $model): bool => str_starts_with($model, 'gpt-5'))
            ?? ($models[0] ?? null);
    }
}
