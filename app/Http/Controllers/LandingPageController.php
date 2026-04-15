<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Landing\LandingPublicStatsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LandingPageController extends Controller
{
    /**
     * Публичная главная: лендинг для гостей, редирект в кабинет для авторизованных.
     */
    public function show(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        if ($user !== null) {
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

        $connect_telegram_url = services()->settings()->getSupportLink();
        $connect_telegram_url = is_string($connect_telegram_url) && $connect_telegram_url !== ''
            ? $connect_telegram_url
            : null;

        return Inertia::render('Landing/Index', [
            'connect_telegram_url' => $connect_telegram_url,
            'landing_stats' => app(LandingPublicStatsService::class)->getSnapshot(),
        ]);
    }
}
