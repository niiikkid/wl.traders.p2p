<?php

namespace App\Http\Controllers;

use App\DTO\Merchant\MerchantCreateDTO;
use App\Enums\DetailType;
use App\Enums\MarketEnum;
use App\Enums\OrderStatus;
use App\Http\Requests\Merchant\StoreRequest;
use App\Http\Requests\Merchant\UpdateCommissionSettingsRequest;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\MerchantResource;
use App\Http\Resources\PaymentGatewayResource;
use App\Models\Category;
use App\Models\Merchant;
use App\Models\Order;
use App\Models\User;
use App\Services\Money\Currency;
use App\Services\Money\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class MerchantController extends Controller
{
    public function index()
    {
        $merchants = MerchantResource::collection($this->merchantsForOwner());

        return Inertia::render('Merchant/Index', compact('merchants'));
    }

    public function indexData(): JsonResponse
    {
        return response()->json(
            MerchantResource::collection($this->merchantsForOwner())->response()->getData(true)
        );
    }

    public function store(StoreRequest $request)
    {
        $merchant = services()->merchant()->create(new MerchantCreateDTO(
            user_id: auth()->id(),
            name: (string) $request->name,
            description: (string) ($request->description ?? ''),
            project_link: (string) ($request->project_link ?? ''),
        ));

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'data' => ['id' => $merchant->id],
            ]);
        }

        return redirect()->route('merchants.index');
    }

    public function updateCallbackURL(Request $request, Merchant $merchant)
    {
        Gate::authorize('access-to-merchant', $merchant);

        $callbackUrlRules = ['nullable', 'string', 'max:256', is_local() ? 'url' : 'url:https'];

        $request->validate([
            'callback_url' => $callbackUrlRules,
            'payout_callback_url' => $callbackUrlRules,
        ]);

        $merchant->update([
            'callback_url' => $request->callback_url,
            'payout_callback_url' => $request->payout_callback_url,
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'merchant' => MerchantResource::make($merchant->fresh(['categories', 'apiCredential']))->resolve(),
            ]);
        }

        return back();
    }

    public function settings(Merchant $merchant): JsonResponse
    {
        Gate::authorize('access-to-merchant', $merchant);

        $user = request()->user();
        $canManageApiCredentials = $user?->hasRole('Super Admin') ?? false;

        if ($canManageApiCredentials) {
            $merchant->apiCredentialOrCreate();
            $merchant->load('categories', 'apiCredential', 'agent');
        } else {
            $merchant->load('categories');
        }

        $paymentGateways = [
            'data' => PaymentGatewayResource::collection(
                queries()->paymentGateway()->getAllActive()
            )->resolve(),
        ];

        return response()->json([
            'merchant' => MerchantResource::make($merchant)->resolve(),
            'commission_settings' => $merchant->getCommissionSettings(),
            'payment_gateways' => $paymentGateways,
            'markets' => $this->getMarkets(),
            'categories' => CategoryResource::collection(Category::orderBy('name')->get())->resolve(),
            'currencies' => $this->getCurrencies(),
            'detail_types' => $this->getDetailTypes(),
            'agents' => $canManageApiCredentials ? $this->getAgents() : [],
        ]);
    }

    public function regenerateApiCredential(Merchant $merchant, string $tokenType): JsonResponse
    {
        Gate::authorize('access-to-merchant', $merchant);
        $user = request()->user();

        if (! ($user?->hasRole('Super Admin') ?? false)) {
            abort(404);
        }

        if (! in_array($tokenType, ['api', 'callback'], true)) {
            abort(404);
        }

        $credential = $merchant->apiCredentialOrCreate();

        if ($tokenType === 'api') {
            $credential->regenerateApiToken();
        } else {
            $credential->regenerateCallbackToken();
        }

        return response()->json([
            'merchant' => MerchantResource::make($merchant->fresh(['categories', 'apiCredential']))->resolve(),
        ]);
    }

    public function updateCommissionSettings(
        UpdateCommissionSettingsRequest $request,
        Merchant $merchant
    ): JsonResponse {
        Gate::authorize('access-to-merchant', $merchant);

        $rawSettings = $request->validated('commission_settings', []);

        $normalized = collect($rawSettings)
            ->map(function (array $setting) {
                $useFlexible = (bool) ($setting['use_flexible_trader_commission_for_orders'] ?? false);
                $traderRateRaw = $setting['trader_commission_rate_for_orders'] ?? null;
                $totalRateRaw = $setting['total_service_commission_rate_for_orders'] ?? null;
                $traderRate = ($traderRateRaw === '' || $traderRateRaw === null) ? null : (float) $traderRateRaw;
                $totalRate = ($totalRateRaw === '' || $totalRateRaw === null) ? null : (float) $totalRateRaw;

                $hasFixed = $traderRate !== null && $totalRate !== null;
                $hasFlexible = $useFlexible && ! empty($setting['trader_commission_tiers_for_orders']) && ! empty($setting['total_service_commission_tiers_for_orders']);

                if (! $hasFixed && ! $hasFlexible) {
                    return null;
                }

                return [
                    'currency' => strtolower((string) $setting['currency']),
                    'detail_type' => (string) $setting['detail_type'],
                    'trader_commission_rate_for_orders' => $hasFixed ? (float) $traderRate : null,
                    'total_service_commission_rate_for_orders' => $hasFixed ? (float) $totalRate : null,
                    'use_flexible_trader_commission_for_orders' => $useFlexible,
                    'trader_commission_tiers_for_orders' => $useFlexible
                        ? array_values($setting['trader_commission_tiers_for_orders'] ?? [])
                        : [],
                    'total_service_commission_tiers_for_orders' => $useFlexible
                        ? array_values($setting['total_service_commission_tiers_for_orders'] ?? [])
                        : [],
                ];
            })
            ->filter()
            ->values()
            ->toArray();

        $merchant->setCommissionSettings($normalized);
        $merchant->save();

        return response()->json([
            'merchant' => MerchantResource::make($merchant->fresh(['categories', 'apiCredential']))->resolve(),
            'commission_settings' => $merchant->fresh()->getCommissionSettings(),
        ]);
    }

    protected function merchantsForOwner(): Collection
    {
        $merchants = Merchant::query()
            ->with('user')
            ->withSum(['orders' => function ($query) {
                $query->where('status', OrderStatus::SUCCESS);
                $query->whereDate('created_at', now()->today());
            }], 'merchant_profit')
            ->where('user_id', auth()->user()->id)
            ->orderByDesc('id')
            ->get();

        return $merchants->transform(function (Merchant $merchant) {
            $merchant->orders_sum_merchant_profit = $merchant->orders_sum_merchant_profit ?? 0;

            return $merchant;
        });
    }

    protected function buildStatistics(Merchant $merchant): array
    {
        $today = Order::query()
            ->where('status', OrderStatus::SUCCESS)
            ->where('merchant_id', $merchant->id)
            ->whereDate('created_at', now()->today());

        $yesterday = Order::query()
            ->where('status', OrderStatus::SUCCESS)
            ->where('merchant_id', $merchant->id)
            ->whereDate('created_at', now()->yesterday());

        $month = Order::query()
            ->where('status', OrderStatus::SUCCESS)
            ->where('merchant_id', $merchant->id)
            ->whereDate('created_at', '>', now()->startOfMonth());

        $total = Order::query()
            ->where('status', OrderStatus::SUCCESS)
            ->where('merchant_id', $merchant->id);

        return [
            'today_profit' => Money::fromUnits($today->sum('merchant_profit') ?? 0, Currency::USDT())->toBeauty(),
            'yesterday_profit' => Money::fromUnits($yesterday->sum('merchant_profit') ?? 0, Currency::USDT())->toBeauty(),
            'month_profit' => Money::fromUnits($month->sum('merchant_profit') ?? 0, Currency::USDT())->toBeauty(),
            'total_profit' => Money::fromUnits($total->sum('merchant_profit') ?? 0, Currency::USDT())->toBeauty(),
            'today_orders_count' => $today->count('id'),
            'yesterday_orders_count' => $yesterday->count('id'),
            'month_orders_count' => $month->count('id'),
            'total_orders_count' => $total->count('id'),
            'currency' => Currency::USDT()->getCode(),
        ];
    }

    protected function getMarkets(): array
    {
        $markets = [];

        foreach (MarketEnum::cases() as $market) {
            $markets[] = [
                'name' => trans("market.name.{$market->value}"),
                'value' => $market->value,
            ];
        }

        return $markets;
    }

    protected function getCurrencies(): array
    {
        return Currency::getAll()
            ->transform(function (Currency $currency) {
                return [
                    'value' => $currency->getCode(),
                    'name' => $currency->getName().' ('.$currency->getSymbol().')',
                    'symbol' => $currency->getSymbol(),
                    'code' => $currency->getCode(),
                ];
            })
            ->values()
            ->toArray();
    }

    protected function getDetailTypes(): array
    {
        $detailTypes = [];

        foreach (DetailType::values() as $detailType) {
            $detailTypes[] = [
                'name' => trans('detail-type.'.$detailType),
                'code' => $detailType,
            ];
        }

        return $detailTypes;
    }

    private function getAgents(): array
    {
        return User::query()
            ->role('Agent')
            ->select(['id', 'email'])
            ->orderBy('email')
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'email' => $user->email,
            ])
            ->toArray();
    }
}
