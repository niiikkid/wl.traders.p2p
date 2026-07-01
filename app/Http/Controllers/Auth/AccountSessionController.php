<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AccountTwoFactorRequest;
use App\Http\Requests\Auth\AddAccountRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccountSessionController extends Controller
{
    public function create(Request $request): Response|RedirectResponse
    {
        if ($this->isImpersonated($request)) {
            return $this->impersonationBlockedRedirect();
        }

        return Inertia::render('Auth/AddAccount', [
            'accounts' => services()->accountSession()->accountsForShare($request, $request->user()),
        ]);
    }

    public function store(AddAccountRequest $request): RedirectResponse
    {
        if ($this->isImpersonated($request)) {
            return $this->impersonationBlockedRedirect();
        }

        $user = $request->authenticatedUser();
        $remember = $request->boolean('remember');

        if ($request->user()?->id === $user->id) {
            services()->accountSession()->rememberCurrentAccount($request, $user, $remember);

            return redirect()->route('dashboard')->with('message', 'Этот аккаунт уже активен.');
        }

        if ($user->google2fa_secret !== null) {
            services()->accountSession()->preparePendingAdd($request, $user, $remember);

            return redirect()->route('account-sessions.2fa');
        }

        services()->accountSession()->addAndSwitch($request, $user, $remember, true);

        return redirect()->route('dashboard')->with('message', 'Аккаунт добавлен и выбран.');
    }

    public function twoFactor(Request $request): Response|RedirectResponse
    {
        if ($this->isImpersonated($request)) {
            return $this->impersonationBlockedRedirect();
        }

        $account = services()->accountSession()->pendingAccountForShare($request);

        if ($account === null) {
            return redirect()->route('account-sessions.create')->with('error', 'Сессия подтверждения истекла. Повторите вход.');
        }

        return Inertia::render('Auth/Account2FA', [
            'account' => $account,
        ]);
    }

    public function verifyTwoFactor(AccountTwoFactorRequest $request): RedirectResponse
    {
        if ($this->isImpersonated($request)) {
            return $this->impersonationBlockedRedirect();
        }

        services()->accountSession()->completePendingTwoFactor($request, $request->oneTimePassword());

        return redirect()->route('dashboard')->with('message', 'Аккаунт подтвержден и выбран.');
    }

    public function switch(Request $request, User $user): RedirectResponse
    {
        if ($this->isImpersonated($request)) {
            return $this->impersonationBlockedRedirect();
        }

        if ($request->user()?->id === $user->id) {
            services()->accountSession()->ensureCurrentAccount($request, $user);

            return redirect()->route('dashboard');
        }

        if (services()->accountSession()->requiresTwoFactor($request, $user)) {
            services()->accountSession()->preparePendingSwitch($request, $user);

            return redirect()->route('account-sessions.2fa');
        }

        services()->accountSession()->switchToAccount($request, $user);

        return redirect()->route('dashboard');
    }

    public function remove(Request $request, User $user): RedirectResponse
    {
        if ($this->isImpersonated($request)) {
            return $this->impersonationBlockedRedirect();
        }

        if ($request->user()?->id === $user->id) {
            return redirect()->back()->with('error', 'Нельзя убрать активный аккаунт. Используйте полный выход.');
        }

        services()->accountSession()->removeAccount($request, $user);

        return redirect()->back()->with('message', 'Аккаунт убран из этого браузера.');
    }

    private function isImpersonated(Request $request): bool
    {
        return (bool) $request->user()?->isImpersonated();
    }

    private function impersonationBlockedRedirect(): RedirectResponse
    {
        return redirect()->back()->with('error', 'Переключение аккаунтов недоступно в режиме Impersonate.');
    }
}
