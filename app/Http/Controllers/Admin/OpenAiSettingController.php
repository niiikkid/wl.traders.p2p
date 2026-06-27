<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\OpenAiSetting\UpdateRequest;
use Illuminate\Http\RedirectResponse;
use RuntimeException;

class OpenAiSettingController extends Controller
{
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
                    ->route('admin.settings.index')
                    ->with('error', $exception->getMessage());
            }
        }

        return redirect()
            ->route('admin.settings.index')
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
                ->route('admin.settings.index')
                ->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('admin.settings.index')
            ->with('message', 'Список моделей OpenAI обновлен.');
    }
}
