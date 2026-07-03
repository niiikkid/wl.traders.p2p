<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PublishThemeRequest;
use Illuminate\Http\JsonResponse;

class AppearanceThemeController extends Controller
{
    /**
     * Publish a theme as the project-wide DaisyUI theme for every user.
     */
    public function publish(PublishThemeRequest $request): JsonResponse
    {
        services()->settings()->updatePublishedTheme($request->toThemePayload());

        return response()->successWithMessage('Тема опубликована и применена ко всему проекту.');
    }

    /**
     * Reset to the default built-in theme for every user.
     */
    public function reset(): JsonResponse
    {
        services()->settings()->updatePublishedTheme(null);

        return response()->successWithMessage('Тема сброшена к стандартной.');
    }
}
