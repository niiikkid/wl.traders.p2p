<?php

namespace App\Http\Controllers;

use App\Enums\MarketEnum;
use App\Enums\OrderStatus;
use App\Http\Requests\Merchant\StoreRequest;
use App\Http\Requests\Merchant\UpdateGatewaySettingsRequest;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\MerchantResource;
use App\Http\Resources\OrderResource;
use App\Http\Resources\PaymentGatewayResource;
use App\Models\Category;
use App\Models\Merchant;
use App\Models\Order;
use App\Services\Money\Currency;
use App\Services\Money\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use App\DTO\Merchant\MerchantCreateDTO;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;

class MerchantController extends Controller
{
    public function index(Request $request)
    {
        $merchants = MerchantResource::collection($this->paginateMerchants($request));

        return Inertia::render('Merchant/Index', compact('merchants'));
    }

    public function indexData(Request $request): JsonResponse
    {
        return response()->json(
            MerchantResource::collection($this->paginateMerchants($request))->response()->getData(true)
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

        $request->validate([
            'callback_url' => ['nullable', 'string', 'url:https', 'max:256'],
            'payout_callback_url' => ['nullable', 'string', 'url:https', 'max:256'],
        ]);

        $merchant->update([
            'callback_url' => $request->callback_url,
            'payout_callback_url' => $request->payout_callback_url,
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'merchant' => MerchantResource::make($merchant->fresh('categories'))->resolve(),
            ]);
        }

        return back();
    }

    public function updateGatewaySettings(UpdateGatewaySettingsRequest $request, Merchant $merchant)
    {
        Gate::authorize('access-to-merchant', $merchant);

        $gatewaySettings = $request->get('gateway_settings', []);

        // Если пользователь не Super Admin, фильтруем настройки
        if (!auth()->user()->hasRole('Super Admin')) {
            $currentSettings = $merchant->gateway_settings;

            foreach ($gatewaySettings as $gatewayId => $settings) {
                // Оставляем только флаг active, остальные настройки берем из текущих
                $filteredSettings = [
                    'active' => $settings['active'] ?? true
                ];

                // Если есть текущие настройки для этого шлюза, сохраняем их
                if (isset($currentSettings[$gatewayId])) {
                    $filteredSettings += array_diff_key(
                        $currentSettings[$gatewayId],
                        ['active' => true]
                    );
                }

                $gatewaySettings[$gatewayId] = $filteredSettings;
            }
        }

        $merchant->update([
            'gateway_settings' => $gatewaySettings,
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'merchant' => MerchantResource::make($merchant->fresh('categories'))->resolve(),
                'gateway_settings' => $merchant->gateway_settings,
            ]);
        }

        return back();
    }

    public function settings(Merchant $merchant): JsonResponse
    {
        Gate::authorize('access-to-merchant', $merchant);

        $merchant->load('categories');

        $paymentGateways = [
            'data' => PaymentGatewayResource::collection(
                queries()->paymentGateway()->getAllActive()
            )->resolve(),
        ];

        return response()->json([
            'merchant' => MerchantResource::make($merchant)->resolve(),
            'gateway_settings' => $merchant->gateway_settings ?? [],
            'payment_gateways' => $paymentGateways,
            'markets' => $this->getMarkets(),
            'categories' => CategoryResource::collection(Category::orderBy('name')->get())->resolve(),
            'currencies' => $this->getCurrencies(),
        ]);
    }

    protected function paginateMerchants(Request $request): LengthAwarePaginator
    {
        $perPage = (int) ($request->get('per_page', 10));

        $merchants = Merchant::query()
            ->with('user')
            ->withSum(['orders' => function ($query) {
                $query->where('status', OrderStatus::SUCCESS);
                $query->whereDate('created_at', now()->today());
            }], 'merchant_profit')
            ->where('user_id', auth()->user()->id)
            ->orderByDesc('id')
            ->paginate($perPage);

        $merchants->transform(function (Merchant $merchant) {
            $merchant->orders_sum_merchant_profit = $merchant->orders_sum_merchant_profit ?? 0;
            return $merchant;
        });

        return $merchants;
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
                    'name' => $currency->getName() . ' (' . $currency->getSymbol() . ')',
                    'symbol' => $currency->getSymbol(),
                    'code' => $currency->getCode(),
                ];
            })
            ->values()
            ->toArray();
    }
}
