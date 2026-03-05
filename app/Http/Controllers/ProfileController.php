<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): Response
    {
        $user = auth()->user();

        $auth2fa = [];

        if (! $user->google2fa_secret) {
            /**
             * @var \PragmaRX\Google2FALaravel\Google2FA $google2fa
             */
            $google2fa = app('pragmarx.google2fa');

            $secret = $google2fa->generateSecretKey();

            $qrCodeUrlInline = $google2fa->getQRCodeInline(
                config('app.name'),
                $user->email,
                $secret,
                220
            );

            $auth2fa =  [
                'qr' => $qrCodeUrlInline,
                'secret' => $secret,
            ];
        }

        // Получаем историю авторизаций пользователя
        $loginHistory = $user->loginHistories()
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => session('status'),
            'auth2fa' => $auth2fa,
            'loginHistory' => $loginHistory,
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

    public function updateAvatar(Request $request): RedirectResponse
    {
        $request->validate([
            'avatar_uuid' => ['required', 'string', 'max:255'],
            'avatar_style' => ['required', 'string', 'max:255'],
        ]);

        $request->user()->update([
            'avatar_uuid' => $request->get('avatar_uuid'),
            'avatar_style' => $request->get('avatar_style'),
        ]);

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
}
