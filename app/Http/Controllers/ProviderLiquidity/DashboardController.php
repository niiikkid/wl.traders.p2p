<?php

declare(strict_types=1);

namespace App\Http\Controllers\ProviderLiquidity;

use App\Http\Controllers\Controller;
use App\Http\Resources\TableCascadeDealResource;
use App\Http\Resources\TableCascadeProviderLogResource;
use App\Models\CascadeProvider;
use App\Models\CascadeProviderLog;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $provider = $this->provider($request);
        $provider?->load('user.wallet');

        $stats = [
            'deals_count' => $provider?->deals()->count() ?? 0,
            'logs_count' => $provider?->logs()->count() ?? 0,
            'provider_balance' => $provider?->user?->wallet?->provider_balance?->toBeauty(),
        ];

        $provider = $this->safeProvider($provider);

        return Inertia::render('ProviderLiquidity/Dashboard', compact('provider', 'stats'));
    }

    public function services(Request $request)
    {
        $provider = $this->provider($request);

        return Inertia::render('ProviderLiquidity/Services', [
            'services' => $provider ? [[
                'id' => $provider->id,
                'code' => $provider->code,
                'name' => $provider->name,
                'provider_type' => $provider->provider_type?->value,
                'is_active' => $provider->is_active,
                'base_url' => $provider->base_url,
                'access_token' => $provider->access_token,
                'merchant_id' => $provider->merchant_id,
                'currency_code' => $provider->currency_code,
                'timeout' => $provider->timeout,
                'verify_ssl' => $provider->verify_ssl,
                'description' => $provider->description,
                'created_at' => $provider->created_at?->toISOString(),
            ]] : [],
        ]);
    }

    public function deals(Request $request)
    {
        $provider = $this->provider($request);

        $deals = $provider
            ? TableCascadeDealResource::collection(
                $provider->deals()
                    ->with(['merchant', 'merchantClient', 'selectedTransaction', 'collateralHolds'])
                    ->latest('id')
                    ->paginate($request->integer('per_page', 10))
                    ->withQueryString()
            )
            : null;

        return Inertia::render('ProviderLiquidity/Deals', compact('deals'));
    }

    public function wallet(Request $request)
    {
        $provider = $this->provider($request);
        $walletModel = $provider?->user?->wallet;
        $wallet = $walletModel ? [
            'id' => $walletModel->id,
            'provider_balance' => $walletModel->provider_balance?->toBeauty(),
            'reserve_balance' => $walletModel->reserve_balance?->toBeauty(),
        ] : null;
        $transactions = $wallet
            ? Transaction::query()
                ->where('wallet_id', $walletModel->id)
                ->latest('id')
                ->paginate($request->integer('per_page', 20))
                ->through(fn (Transaction $transaction) => [
                    'id' => $transaction->id,
                    'amount' => $transaction->amount?->toBeauty(),
                    'direction' => $transaction->direction?->value,
                    'type' => $transaction->type?->value,
                    'created_at' => $transaction->created_at?->toISOString(),
                ])
                ->withQueryString()
            : null;

        return Inertia::render('ProviderLiquidity/Wallet', compact('wallet', 'transactions'));
    }

    public function logs(Request $request)
    {
        $provider = $this->provider($request);

        $logs = $provider
            ? TableCascadeProviderLogResource::collection(
                CascadeProviderLog::query()
                    ->where('provider_id', $provider->id)
                    ->with(['cascadeDeal', 'cascadeTransaction', 'provider'])
                    ->latest('id')
                    ->paginate($request->integer('per_page', 20))
                    ->withQueryString()
            )
            : null;

        return Inertia::render('ProviderLiquidity/Logs', compact('logs'));
    }

    private function provider(Request $request): ?CascadeProvider
    {
        $user = $request->user();

        if (! $user) {
            return null;
        }

        if ($user->hasRole('Super Admin')) {
            return CascadeProvider::query()->whereNotNull('user_id')->first();
        }

        return CascadeProvider::query()
            ->where('user_id', $user->id)
            ->first();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function safeProvider(?CascadeProvider $provider): ?array
    {
        if (! $provider) {
            return null;
        }

        return [
            'id' => $provider->id,
            'code' => $provider->code,
            'name' => $provider->name,
            'provider_type' => $provider->provider_type?->value,
            'created_at' => $provider->created_at?->toISOString(),
        ];
    }
}
