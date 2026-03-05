<?php

namespace App\Http\Controllers;

use App\Contracts\MainPageCacheServiceContract;
use Inertia\Inertia;

class MainPageController extends Controller
{
    public function __construct(
        private readonly MainPageCacheServiceContract $mainPageCacheService,
    ) {
    }

    public function merchant()
    {
        $stats = $this->mainPageCacheService->rememberMerchant(auth()->user());

        return Inertia::render('MainPage/Merchant/Index', $stats);
    }

    public function trader()
    {
        $user = auth()->user();
        $stats = $this->mainPageCacheService->rememberTrader($user);

        $tempVip = $user->getTempVipProgressData();

        return Inertia::render('MainPage/Trader/Index', [
            ...$stats,
            'tempVip' => $tempVip,
        ]);
    }

    public function leader()
    {
        $stats = $this->mainPageCacheService->rememberLeader(auth()->user());

        return Inertia::render('MainPage/Leader/Index', $stats);
    }

    public function admin()
    {
        // Получаем список мерчантов для фильтра
        $merchants = \App\Models\Merchant::query()
            ->select(['id', 'name'])
            ->orderBy('name')
            ->get();

        // Получаем merchant_id из запроса, если он есть
        $merchantId = request()->get('merchant_id');

        $stats = $this->mainPageCacheService->rememberAdmin(auth()->user(), $merchantId);
        $stats['merchants'] = $merchants;
        $stats['selectedMerchantId'] = $merchantId;

        return Inertia::render('MainPage/Admin/Index', $stats);
    }
}
