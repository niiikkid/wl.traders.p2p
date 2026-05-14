<?php

namespace App\Contracts;

use App\Models\OpenAiSetting;

interface OpenAiServiceContract
{
    public function getSettings(): OpenAiSetting;

    public function updateSettings(?string $apiKey, ?string $selectedModel): OpenAiSetting;

    public function refreshModels(?string $apiKey = null): OpenAiSetting;

    public function prompt(string $prompt, ?string $systemPrompt = null, ?string $model = null): string;

    public function promptRaw(string $prompt, ?string $systemPrompt = null, ?string $model = null): array;
}
