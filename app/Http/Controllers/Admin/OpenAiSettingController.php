<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\OpenAiSetting\PromptRequest;
use App\Http\Requests\Admin\OpenAiSetting\UpdateRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class OpenAiSettingController extends Controller
{
    public function index(): Response
    {
        $setting = services()->openAi()->getSettings();

        return Inertia::render('Admin/OpenAi/Index', [
            'setting' => [
                'has_api_key' => $setting->hasApiKey(),
                'selected_model' => $setting->selected_model,
                'available_models' => $setting->available_models ?? [],
                'models_loaded_at' => $setting->models_loaded_at?->toDateTimeString(),
            ],
            'test_form' => [
                'model' => old('model', $setting->selected_model),
                'system_prompt' => old('system_prompt', ''),
                'user_prompt' => old('user_prompt', ''),
            ],
            'test_response' => request()->session()->get('open_ai_test_response'),
        ]);
    }

    public function update(UpdateRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        services()->openAi()->updateSettings(
            apiKey: $validated['api_key'] ?? null,
            selectedModel: $validated['selected_model'] ?? null,
        );

        if (! empty($validated['api_key'])) {
            try {
                services()->openAi()->refreshModels();
            } catch (RuntimeException $exception) {
                return redirect()
                    ->route('admin.open-ai.index')
                    ->with('error', $exception->getMessage());
            }
        }

        return redirect()
            ->route('admin.open-ai.index')
            ->with('message', 'Настройки OpenAI сохранены.');
    }

    public function refreshModels(UpdateRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        services()->openAi()->updateSettings(
            apiKey: $validated['api_key'] ?? null,
            selectedModel: $validated['selected_model'] ?? null,
        );

        try {
            services()->openAi()->refreshModels();
        } catch (RuntimeException $exception) {
            return redirect()
                ->route('admin.open-ai.index')
                ->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('admin.open-ai.index')
            ->with('message', 'Список моделей OpenAI обновлен.');
    }

    public function prompt(PromptRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        try {
            $response = services()->openAi()->promptRaw(
                prompt: $validated['user_prompt'],
                systemPrompt: $validated['system_prompt'],
                model: $validated['model'],
            );
        } catch (RuntimeException $exception) {
            return redirect()
                ->route('admin.open-ai.index')
                ->withInput()
                ->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('admin.open-ai.index')
            ->withInput()
            ->with('open_ai_test_response', json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
