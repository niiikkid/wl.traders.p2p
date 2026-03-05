<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class Check2FACodeController extends Controller
{
    public function check()
    {
        if (! auth()->user()->google2fa_secret || session()->get('user_2fa_passed') === true) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('Auth/Auth2FA');
    }

    public function validate(Request $request)
    {
        if (! auth()->user()->google2fa_secret || session()->get('user_2fa_passed') === true) {
            return redirect()->route('dashboard');
        }

        $request->validate([
            'one_time_password' => ['required', 'numeric'],
        ]);

        /**
         * @var \PragmaRX\Google2FALaravel\Google2FA $google2fa
         */
        $google2fa = app('pragmarx.google2fa');
        $user = auth()->user();

        $opt = $google2fa->getCurrentOtp($user->google2fa_secret);

        if ((int)$opt === (int) $request->input('one_time_password')) {
            session()->put('user_2fa_passed', true);

            return redirect()->route('dashboard');
        }

        return redirect()->route('auth.2fa')->with('error', 'Неверный 2fa код.');
    }
}
