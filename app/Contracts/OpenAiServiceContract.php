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

    /**
     * Extract plain assistant text from OpenAI Responses API JSON payload (`output` → `message` → `output_text` → `text`).
     */
    public function assistantOutputTextFromResponse(array $response): string;
}
