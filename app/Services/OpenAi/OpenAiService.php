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
        $response = $this->promptRaw($prompt, $systemPrompt, $model);

        return $this->assistantOutputTextFromResponse($response);
    }

    public function assistantOutputTextFromResponse(array $response): string
    {
        $parts = [];

        foreach ($response['output'] ?? [] as $item) {
            if (($item['type'] ?? '') !== 'message') {
                continue;
            }

            foreach ($item['content'] ?? [] as $block) {
                if (($block['type'] ?? '') !== 'output_text') {
                    continue;
                }

                $text = $block['text'] ?? null;
                if (is_string($text) && $text !== '') {
                    $parts[] = $text;
                }
            }
        }

        return implode("\n", $parts);
    }

    public function promptRaw(string $prompt, ?string $systemPrompt = null, ?string $model = null): array
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

        $response = $this->client($setting->api_key, timeout: 90)->post('/responses', [
            'model' => $selectedModel,
            'input' => $input,
        ]);

        if (! $response->successful()) {
            throw new RuntimeException($response->json('error.message') ?: 'OpenAI API вернул ошибку.');
        }

        return $response->json();
    }

    public function generateImage(string $prompt, string $size = '1024x1024'): string
    {
        $setting = $this->getSettings();

        if (! $setting->hasApiKey()) {
            throw new RuntimeException('API ключ OpenAI не задан.');
        }

        $response = $this->client($setting->api_key, timeout: 120)->post('/images/generations', [
            'model' => $this->preferredImageModel(),
            'prompt' => $prompt,
            'size' => $size,
            'n' => 1,
        ]);

        if (! $response->successful()) {
            throw new RuntimeException($response->json('error.message') ?: 'OpenAI Image API вернул ошибку.');
        }

        $base64 = $response->json('data.0.b64_json');

        if (! is_string($base64) || $base64 === '') {
            throw new RuntimeException('OpenAI Image API не вернул изображение.');
        }

        $binary = base64_decode($base64, true);

        if ($binary === false || $binary === '') {
            throw new RuntimeException('Не удалось декодировать изображение от OpenAI.');
        }

        return $binary;
    }

    private function client(string $apiKey, int $timeout = 30): PendingRequest
    {
        return Http::baseUrl(self::BASE_URL)
            ->acceptJson()
            ->asJson()
            ->withToken($apiKey)
            ->connectTimeout(10)
            ->timeout($timeout);
    }

    private function preferredImageModel(): string
    {
        return 'gpt-image-1-mini';
    }

    private function preferredModel(array $models): ?string
    {
        return collect($models)->first(fn (string $model): bool => str_starts_with($model, 'gpt-5.5'))
            ?? collect($models)->first(fn (string $model): bool => str_starts_with($model, 'gpt-5'))
            ?? ($models[0] ?? null);
    }
}
