<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class Check2FACodeController extends Controller
{
    public function check(Request $request)
    {
        $user = $request->user();

        if (! $user?->google2fa_secret || ! services()->accountSession()->requiresTwoFactor($request, $user)) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('Auth/Auth2FA');
    }

    public function validate(Request $request)
    {
        $user = $request->user();

        if (! $user?->google2fa_secret || ! services()->accountSession()->requiresTwoFactor($request, $user)) {
            return redirect()->route('dashboard');
        }

        $request->validate([
            'one_time_password' => ['required', 'string', 'size:6'],
        ]);

        if (services()->accountSession()->verifyTwoFactorCode($user, (string) $request->input('one_time_password'))) {
            services()->accountSession()->markCurrentTwoFactorPassed($request, $user);

            return redirect()->route('dashboard');
        }

        return redirect()->route('auth.2fa')->with('error', 'Неверный 2fa код.');
    }
}
