<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;
use PragmaRX\Google2FALaravel\Google2FA;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user();

        $auth2fa = [];

        if (! $user->google2fa_secret) {
            /**
             * @var Google2FA $google2fa
             */
            $google2fa = app('pragmarx.google2fa');

            $secret = $google2fa->generateSecretKey();

            $qrCodeUrlInline = $google2fa->getQRCodeInline(
                config('app.name'),
                $user->email,
                $secret,
                220
            );

            $auth2fa = [
                'qr' => $qrCodeUrlInline,
                'secret' => $secret,
            ];
        }

        $loginHistory = $user->loginHistories()
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 10))
            ->withQueryString();

        $openAiSetting = services()->openAi()->getSettings();

        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => session('status'),
            'auth2fa' => $auth2fa,
            'loginHistory' => $loginHistory,
            'loginHistoryLoggingEnabled' => (bool) $user->login_history_logging_enabled,
            'canManageLoginHistoryLogging' => $user->hasRole('Super Admin'),
            'avatar' => [
                'url' => $user->avatarUrl(),
                'caption' => $user->avatar_caption,
                'status' => $user->avatar_generation_status,
                'error' => $user->avatar_generation_error,
                'generated_at' => $user->avatar_generated_at?->toISOString(),
            ],
            'openAiConfigured' => $openAiSetting->hasApiKey()
                && is_string($openAiSetting->selected_model)
                && $openAiSetting->selected_model !== '',
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        // Нет полей для обновления
        return Redirect::route('profile.edit');
    }

    public function updateAuth2fa(Request $request): RedirectResponse
    {
        $request->validate([
            'secret' => ['required', 'string', 'max:255'],
        ]);

        $request->user()->update([
            'google2fa_secret' => $request->get('secret'),
        ]);

        return Redirect::route('profile.edit');
    }

    public function logoutOtherDevices(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
        ]);

        Auth::logoutOtherDevices($validated['current_password']);

        $request->session()->regenerate();

        return Redirect::route('profile.edit')->with('status', 'other-sessions-logged-out');
    }

    public function regenerateAvatar(Request $request): JsonResponse
    {
        $user = $request->user();

        try {
            $result = services()->user()->requestAvatarGeneration($user);
        } catch (\Throwable $exception) {
            return response()->failWithMessage(
                $exception->getMessage() ?: 'Не удалось сгенерировать аватар.',
                422,
            );
        }

        return response()->success([
            'status' => $result['status'],
        ]);
    }

    public function toggleLoginHistoryLogging(Request $request): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user->hasRole('Super Admin'), 403);

        if ($user->login_history_logging_enabled) {
            $user->update(['login_history_logging_enabled' => false]);

            return Redirect::route('profile.edit')->with('status', 'login-history-logging-disabled');
        }

        $user->update(['login_history_logging_enabled' => true]);

        return Redirect::route('profile.edit')->with('status', 'login-history-logging-enabled');
    }
}
