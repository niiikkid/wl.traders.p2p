<?php

namespace App\Http\Controllers\TeamLeader;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\ReferralResource;
use App\Models\Order;
use App\Models\User;
use App\Services\Money\Currency;
use App\Services\Money\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ReferralController extends Controller
{
    /**
     * Отображает список рефералов команды лидера.
     */
    public function index()
    {
        $referrals = User::query()
            ->where('team_leader_id', auth()->id())
            ->latest('created_at')
            ->paginate(request()->per_page ?? 10);

        // Получаем статистику по заказам для каждого реферала
        $referralsIds = $referrals->pluck('id');

        // Подсчет количества сделок и прибыли
        $referralStats = Order::select('trader_id')
            ->selectRaw('COUNT(*) as orders_count')
            ->selectRaw('SUM(team_leader_profit) as total_team_leader_profit')
            ->where('status', OrderStatus::SUCCESS)
            ->whereIn('trader_id', $referralsIds)
            ->whereNotNull('team_leader_id') // Учитываем только те сделки, где был назначен Team Leader
            ->where('team_leader_id', auth()->id()) // И этот Team Leader - текущий пользователь
            ->groupBy('trader_id')
            ->get()
            ->keyBy('trader_id');

        // Преобразуем данные в формат, удобный для отображения
        $enrichedReferrals = $referrals->through(function ($referral) use ($referralStats) {
            $stats = $referralStats[$referral->id] ?? null;
            $referral->orders_count = $stats ? $stats->orders_count : 0;
            $referral->total_team_leader_profit = $stats
                ? Money::fromUnits($stats->total_team_leader_profit, Currency::USDT())
                : Money::zero(Currency::USDT());
            return $referral;
        });

        return Inertia::render('Referral/Index', [
            'referrals' => ReferralResource::collection($enrichedReferrals)
        ]);
    }
}
