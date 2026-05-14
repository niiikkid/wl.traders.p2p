<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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
}
