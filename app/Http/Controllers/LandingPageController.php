<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LandingPageController extends Controller
{
    /**
     * Редирект авторизованного пользователя в соответствующий раздел кабинета.
     */
    public static function resolveAuthenticatedHome(Request $request): ?RedirectResponse
    {
        $user = $request->user();
        if ($user === null) {
            return null;
        }

        if ($user->hasRole('Merchant')) {
            return redirect()->route('merchant.main.index');
        }
        if ($user->hasRole('Trader')) {
            return redirect()->route('trader.main.index');
        }
        if ($user->hasRole('Support')) {
            return redirect()->route('support.users.index');
        }
        if ($user->hasRole('Team Leader')) {
            return redirect()->route('leader.main.index');
        }
        if ($user->hasRole('Merchant Support')) {
            return redirect()->route('merchant-support.payments.index');
        }

        return redirect()->route('admin.main.index');
    }

    /**
     * Публичная главная: лендинг для гостей, редирект в кабинет для авторизованных.
     */
    public function show(Request $request): Response|RedirectResponse
    {
        $authenticatedHome = self::resolveAuthenticatedHome($request);
        if ($authenticatedHome !== null) {
            return $authenticatedHome;
        }

        $connect_telegram_url = services()->settings()->getLandingTelegramLink();
        $connect_telegram_url = is_string($connect_telegram_url) && $connect_telegram_url !== ''
            ? $connect_telegram_url
            : null;

        return Inertia::render('Landing/Index', [
            'connect_telegram_url' => $connect_telegram_url,
        ]);
    }
}
