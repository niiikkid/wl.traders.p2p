<?php

namespace App\Http\Controllers;

use App\Contracts\MainPageCacheServiceContract;
use App\Contracts\MainPageStatsServiceContract;
use App\Enums\BalanceType;
use App\Enums\DetailType;
use App\Models\Merchant;
use App\Models\Order;
use App\Models\PaymentDetail;
use App\Models\PaymentGateway;
use App\Models\Payout\Payout;
use App\Models\User;
use App\Services\EnabledCards\MinAmountStatsService;
use App\Services\Money\Currency;
use App\Services\Money\Money;
use App\Services\ProviderLiquidity\ProviderLiquidityDashboardService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;

class MainPageController extends Controller
{
    public function __construct(
        private readonly MainPageCacheServiceContract $mainPageCacheService,
        private readonly MainPageStatsServiceContract $mainPageStatsService,
        private readonly MinAmountStatsService $minAmountStatsService,
    ) {}

    public function merchant()
    {
        $user = auth()->user();
        $periodPreset = (string) request()->get('period', 'month');
        $dateFrom = request()->get('date_from');
        $dateTo = request()->get('date_to');
        $mode = (string) request()->input('mode', 'deals');
        $activeStatsMode = $mode === 'payouts' ? 'payouts' : 'deals';

        if ($activeStatsMode === 'payouts') {
            $filters = [
                'merchantIds' => request()->input('merchant_ids', []),
            ];

            $stats = $this->mainPageStatsService->buildMerchantPayoutMainPageStats(
                $user,
                $periodPreset,
                $dateFrom !== null ? (string) $dateFrom : null,
                $dateTo !== null ? (string) $dateTo : null,
                $filters,
            );
        } else {
            $filters = [
                'paymentMethodIds' => request()->input('payment_method_ids', []),
                'merchantIds' => request()->input('merchant_ids', []),
            ];

            $stats = $this->mainPageStatsService->buildMerchantMainPageStats(
                $user,
                $periodPreset,
                $dateFrom !== null ? (string) $dateFrom : null,
                $dateTo !== null ? (string) $dateTo : null,
                $filters,
            );
        }

        return Inertia::render('MainPage/Merchant/Index', [
            ...$stats,
            'activeStatsMode' => $activeStatsMode,
            'enabledCardsMinAmountStatistics' => $this->minAmountStatsService->buildForMerchantUser($user),
        ]);
    }

    public function trader()
    {
        $user = auth()->user();
        $periodPreset = (string) request()->get('period', 'month');
        $dateFrom = request()->get('date_from');
        $dateTo = request()->get('date_to');
        $mode = (string) request()->input('mode', 'deals');
        $activeStatsMode = $mode === 'payouts' ? 'payouts' : 'deals';

        if ($activeStatsMode === 'payouts') {
            $stats = $this->mainPageStatsService->buildTraderPayoutMainPageStats(
                $user,
                $periodPreset,
                $dateFrom !== null ? (string) $dateFrom : null,
                $dateTo !== null ? (string) $dateTo : null,
            );
        } else {
            $filters = [
                'paymentMethodIds' => request()->input('payment_method_ids', []),
                'paymentDetailIds' => request()->input('payment_detail_ids', []),
            ];

            $stats = $this->mainPageStatsService->buildTraderMainPageStats(
                $user,
                $periodPreset,
                $dateFrom !== null ? (string) $dateFrom : null,
                $dateTo !== null ? (string) $dateTo : null,
                $filters,
            );
        }

        $balance = $user->wallet
            ? services()->wallet()->getTotalAvailableBalance($user->wallet, BalanceType::TRUST)
            : Money::fromUnits(0, Currency::USDT());
        $stats['statistics']['balance'] = $balance->toBeauty();

        $walletStats = services()->wallet()->getWalletStats($user->wallet)->toArray();

        return Inertia::render('MainPage/Trader/Index', [
            ...$stats,
            'activeStatsMode' => $activeStatsMode,
            'walletStats' => $walletStats,
        ]);
    }

    public function leader()
    {
        $stats = $this->mainPageCacheService->rememberLeader(auth()->user());

        return Inertia::render('MainPage/Leader/Index', $stats);
    }

    public function admin()
    {
        $merchantId = request()->get('merchant_id');
        $periodPreset = (string) request()->get('period', 'month');
        $dateFrom = request()->get('date_from');
        $dateTo = request()->get('date_to');
        $mode = (string) request()->input('mode', 'deals');
        $activeStatsMode = $mode === 'payouts' ? 'payouts' : 'deals';

        $filters = [
            'traderIds' => request()->input('trader_ids', []),
            'paymentMethodIds' => request()->input('payment_method_ids', []),
            'paymentDetailIds' => request()->input('payment_detail_ids', []),
            'merchantIds' => request()->input('merchant_ids', []),
        ];

        $scopedMerchantId = is_numeric($merchantId) ? (int) $merchantId : null;

        if ($activeStatsMode === 'payouts') {
            $stats = $this->mainPageStatsService->buildAdminPayoutMainPageStats(
                auth()->user(),
                $scopedMerchantId,
                $periodPreset,
                $dateFrom !== null ? (string) $dateFrom : null,
                $dateTo !== null ? (string) $dateTo : null,
                $filters,
            );
        } else {
            $stats = $this->mainPageStatsService->buildAdminStats(
                auth()->user(),
                $scopedMerchantId,
                $periodPreset,
                $dateFrom !== null ? (string) $dateFrom : null,
                $dateTo !== null ? (string) $dateTo : null,
                $filters,
            );
        }

        return Inertia::render('MainPage/Admin/Index', [
            ...$stats,
            'activeStatsMode' => $activeStatsMode,
        ]);
    }

    /**
     * Главная страница кабинета провайдера ликвидности (каскад).
     */
    public function providerLiquidity(Request $request, ProviderLiquidityDashboardService $providerLiquidityDashboardService)
    {
        return Inertia::render(
            'MainPage/ProviderLiquidity/Index',
            $providerLiquidityDashboardService->buildMainPageProps($request),
        );
    }

    public function adminFilterOptions(Request $request, string $type): JsonResponse
    {
        $search = trim((string) $request->get('query', ''));
        $statsMode = (string) $request->get('mode', 'deals');
        $fromPayouts = $statsMode === 'payouts';
        $selectedIds = collect($request->input('selected_ids', []))
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->toArray();

        $options = match ($type) {
            'trader' => $fromPayouts
                ? $this->searchTradersFromPayouts($search, $selectedIds)
                : $this->searchTraders($search, $selectedIds),
            'payment_method' => $this->searchPaymentMethods($search, $selectedIds),
            'payment_detail' => $this->searchPaymentDetails($search, $selectedIds),
            'merchant' => $fromPayouts
                ? $this->searchMerchantsFromPayouts($search, $selectedIds)
                : $this->searchMerchants($search, $selectedIds),
            default => collect(),
        };

        return response()->json($options->values());
    }

    public function traderFilterOptions(Request $request, string $type): JsonResponse
    {
        $user = auth()->user();
        $search = trim((string) $request->get('query', ''));
        $selectedIds = collect($request->input('selected_ids', []))
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->toArray();

        $options = match ($type) {
            'payment_method' => $this->searchTraderPaymentMethods($user, $search, $selectedIds),
            'payment_detail' => $this->searchTraderPaymentDetails($user, $search, $selectedIds),
            default => collect(),
        };

        return response()->json($options->values());
    }

    public function merchantFilterOptions(Request $request, string $type): JsonResponse
    {
        $user = auth()->user();
        $search = trim((string) $request->get('query', ''));
        $selectedIds = collect($request->input('selected_ids', []))
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->toArray();
        $merchantIds = collect($request->input('merchant_ids', []))
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->toArray();
        $dateFrom = $this->parseFilterDate($request->input('date_from'));
        $dateTo = $this->parseFilterDate($request->input('date_to'));

        if ($dateFrom && $dateTo && $dateFrom->gt($dateTo)) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }

        $options = match ($type) {
            'payment_method' => $this->searchMerchantPaymentMethods($user, $search, $selectedIds, $merchantIds, $dateFrom, $dateTo),
            'merchant' => $this->searchUserMerchants($user, $search, $selectedIds),
            default => collect(),
        };

        return response()->json($options->values());
    }

    private function searchTradersFromPayouts(string $search, array $selectedIds): Collection
    {
        $query = User::query()
            ->whereIn('id', Payout::query()->whereNotNull('trader_id')->select('trader_id')->distinct())
            ->select(['id', 'name', 'email']);

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('id', 'like', "%{$search}%");
            });
        }

        return $this->mergeSelectedFirst(
            $query->limit(10)->get()->map(fn (User $user) => [
                'value' => $user->id,
                'label' => $user->name ?: $user->email,
            ]),
            User::query()->whereIn('id', $selectedIds)->get()->map(fn (User $user) => [
                'value' => $user->id,
                'label' => $user->name ?: $user->email,
            ]),
        );
    }

    private function searchMerchantsFromPayouts(string $search, array $selectedIds): Collection
    {
        $query = Merchant::query()
            ->whereIn('id', Payout::query()->select('merchant_id')->distinct())
            ->select(['id', 'name']);

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('id', 'like', "%{$search}%");
            });
        }

        return $this->mergeSelectedFirst(
            $query->limit(10)->get()->map(fn (Merchant $merchant) => [
                'value' => $merchant->id,
                'label' => $merchant->name,
            ]),
            Merchant::query()->whereIn('id', $selectedIds)->get()->map(fn (Merchant $merchant) => [
                'value' => $merchant->id,
                'label' => $merchant->name,
            ]),
        );
    }

    private function searchTraders(string $search, array $selectedIds): Collection
    {
        $query = User::query()
            ->whereIn('id', Order::query()->select('trader_id')->distinct())
            ->select(['id', 'name', 'email']);

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('id', 'like', "%{$search}%");
            });
        }

        return $this->mergeSelectedFirst(
            $query->limit(10)->get()->map(fn (User $user) => [
                'value' => $user->id,
                'label' => $user->name ?: $user->email,
            ]),
            User::query()->whereIn('id', $selectedIds)->get()->map(fn (User $user) => [
                'value' => $user->id,
                'label' => $user->name ?: $user->email,
            ]),
        );
    }

    private function searchPaymentMethods(string $search, array $selectedIds): Collection
    {
        $query = PaymentGateway::query()
            ->whereIn('id', Order::query()->select('payment_gateway_id')->distinct())
            ->select(['id', 'name', 'code', 'currency']);

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('id', 'like', "%{$search}%");
            });
        }

        return $this->mergeSelectedFirst(
            $query->limit(10)->get()->map(fn (PaymentGateway $gateway) => [
                'value' => $gateway->id,
                'label' => "{$gateway->name} ".strtoupper((string) $gateway->currency?->getCode()),
            ]),
            PaymentGateway::query()->whereIn('id', $selectedIds)->get()->map(fn (PaymentGateway $gateway) => [
                'value' => $gateway->id,
                'label' => "{$gateway->name} ".strtoupper((string) $gateway->currency?->getCode()),
            ]),
        );
    }

    private function searchPaymentDetails(string $search, array $selectedIds): Collection
    {
        $query = PaymentDetail::query()
            ->whereIn('id', Order::query()->select('payment_detail_id')->distinct())
            ->select(['id', 'name', 'detail', 'detail_type']);

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('detail', 'like', "%{$search}%")
                    ->orWhere('id', 'like', "%{$search}%");
            });
        }

        return $this->mergeSelectedFirst(
            $query->limit(10)->get()->map(fn (PaymentDetail $detail) => [
                'value' => $detail->id,
                'label' => $detail->name,
                'subtitle' => $this->formatPaymentDetailSubtitle($detail),
            ]),
            PaymentDetail::query()->whereIn('id', $selectedIds)->get()->map(fn (PaymentDetail $detail) => [
                'value' => $detail->id,
                'label' => $detail->name,
                'subtitle' => $this->formatPaymentDetailSubtitle($detail),
            ]),
        );
    }

    private function searchMerchants(string $search, array $selectedIds): Collection
    {
        $query = Merchant::query()
            ->whereIn('id', Order::query()->select('merchant_id')->distinct())
            ->select(['id', 'name']);

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('id', 'like', "%{$search}%");
            });
        }

        return $this->mergeSelectedFirst(
            $query->limit(10)->get()->map(fn (Merchant $merchant) => [
                'value' => $merchant->id,
                'label' => $merchant->name,
            ]),
            Merchant::query()->whereIn('id', $selectedIds)->get()->map(fn (Merchant $merchant) => [
                'value' => $merchant->id,
                'label' => $merchant->name,
            ]),
        );
    }

    private function searchTraderPaymentMethods(User $user, string $search, array $selectedIds): Collection
    {
        $detailIds = PaymentDetail::query()
            ->where('user_id', $user->id)
            ->pluck('id');

        $allowedGatewayIds = $detailIds->isEmpty()
            ? collect()
            : DB::table('payment_detail_payment_gateway')
                ->whereIn('payment_detail_id', $detailIds)
                ->distinct()
                ->pluck('payment_gateway_id');

        $query = PaymentGateway::query()
            ->whereIn('id', $allowedGatewayIds)
            ->select(['id', 'name', 'code', 'currency'])
            ->orderBy('name')
            ->orderBy('id');

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('id', 'like', "%{$search}%");
            });
        }

        return $this->mergeSelectedFirst(
            $query->get()->map(fn (PaymentGateway $gateway) => [
                'value' => $gateway->id,
                'label' => "{$gateway->name} ".strtoupper((string) $gateway->currency?->getCode()),
            ]),
            PaymentGateway::query()
                ->whereIn('id', $selectedIds)
                ->whereIn('id', $allowedGatewayIds)
                ->get()
                ->map(fn (PaymentGateway $gateway) => [
                    'value' => $gateway->id,
                    'label' => "{$gateway->name} ".strtoupper((string) $gateway->currency?->getCode()),
                ]),
        );
    }

    private function searchTraderPaymentDetails(User $user, string $search, array $selectedIds): Collection
    {
        $query = PaymentDetail::query()
            ->where('user_id', $user->id)
            ->select(['id', 'name', 'detail', 'detail_type', 'archived_at'])
            ->orderBy('name')
            ->orderBy('id');

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('detail', 'like', "%{$search}%")
                    ->orWhere('id', 'like', "%{$search}%");
            });
        }

        return $this->mergeSelectedFirst(
            $query->get()->map(fn (PaymentDetail $detail) => $this->mapTraderPaymentDetailFilterOption($detail)),
            PaymentDetail::query()
                ->where('user_id', $user->id)
                ->whereIn('id', $selectedIds)
                ->select(['id', 'name', 'detail', 'detail_type', 'archived_at'])
                ->get()
                ->map(fn (PaymentDetail $detail) => $this->mapTraderPaymentDetailFilterOption($detail)),
        );
    }

    /**
     * @return array{value: int, label: string, subtitle: string, is_archived: bool}
     */
    private function mapTraderPaymentDetailFilterOption(PaymentDetail $detail): array
    {
        return [
            'value' => $detail->id,
            'label' => $detail->name,
            'subtitle' => $this->formatPaymentDetailSubtitle($detail),
            'is_archived' => $detail->archived_at !== null,
        ];
    }

    private function searchMerchantPaymentMethods(
        User $user,
        string $search,
        array $selectedIds,
        array $merchantIds = [],
        ?Carbon $dateFrom = null,
        ?Carbon $dateTo = null,
    ): Collection {
        $allowedMerchantIds = Merchant::query()
            ->where('user_id', $user->id)
            ->pluck('id');

        if ($allowedMerchantIds->isEmpty()) {
            return collect();
        }

        $scopedMerchantIds = collect($merchantIds)
            ->filter(fn (int $id) => $allowedMerchantIds->contains($id))
            ->values();

        if ($scopedMerchantIds->isEmpty()) {
            $scopedMerchantIds = $allowedMerchantIds->values();
        }

        $gatewayIdsQuery = Order::query()
            ->whereIn('merchant_id', $scopedMerchantIds)
            ->distinct();

        if ($dateFrom && $dateTo) {
            $gatewayIdsQuery->whereBetween('created_at', [
                $dateFrom->copy()->startOfDay(),
                $dateTo->copy()->endOfDay(),
            ]);
        }

        $allowedGatewayIds = $gatewayIdsQuery->pluck('payment_gateway_id');

        $query = PaymentGateway::query()
            ->whereIn('id', $allowedGatewayIds)
            ->select(['id', 'name', 'code', 'currency']);

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('id', 'like', "%{$search}%");
            });
        }

        return $this->mergeSelectedFirst(
            $query->limit(10)->get()->map(fn (PaymentGateway $gateway) => [
                'value' => $gateway->id,
                'label' => "{$gateway->name} ".strtoupper((string) $gateway->currency?->getCode()),
            ]),
            PaymentGateway::query()
                ->whereIn('id', $selectedIds)
                ->whereIn('id', $allowedGatewayIds)
                ->get()
                ->map(fn (PaymentGateway $gateway) => [
                    'value' => $gateway->id,
                    'label' => "{$gateway->name} ".strtoupper((string) $gateway->currency?->getCode()),
                ]),
        );
    }

    private function parseFilterDate(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function searchUserMerchants(User $user, string $search, array $selectedIds): Collection
    {
        $query = Merchant::query()
            ->where('user_id', $user->id)
            ->select(['id', 'name']);

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('id', 'like', "%{$search}%");
            });
        }

        return $this->mergeSelectedFirst(
            $query->limit(10)->get()->map(fn (Merchant $merchant) => [
                'value' => $merchant->id,
                'label' => $merchant->name,
            ]),
            Merchant::query()
                ->where('user_id', $user->id)
                ->whereIn('id', $selectedIds)
                ->get()
                ->map(fn (Merchant $merchant) => [
                    'value' => $merchant->id,
                    'label' => $merchant->name,
                ]),
        );
    }

    private function mergeSelectedFirst(Collection $searchResults, Collection $selectedResults): Collection
    {
        return $selectedResults
            ->concat($searchResults)
            ->unique('value')
            ->values();
    }

    private function formatPaymentDetailSubtitle(PaymentDetail $detail): string
    {
        $rawDetail = trim((string) $detail->detail);
        if ($rawDetail === '') {
            return '';
        }

        $detailType = $detail->detail_type instanceof DetailType
            ? $detail->detail_type
            : DetailType::tryFrom((string) $detail->detail_type);

        $shortValue = match ($detailType) {
            DetailType::CARD, DetailType::ACCOUNT_NUMBER, DetailType::IBAN_UAH => '•••• '.Str::of($rawDetail)->replaceMatches('/\D+/', '')->substr(-4),
            DetailType::PHONE, DetailType::MOBILE_COMMERCE => $this->maskPhoneValue($rawDetail),
            default => Str::limit($rawDetail, 18, '...'),
        };

        return (string) $shortValue;
    }

    private function maskPhoneValue(string $value): string
    {
        $normalized = preg_replace('/\s+/', '', $value);
        if (! $normalized) {
            return '';
        }

        $length = mb_strlen($normalized);
        if ($length <= 4) {
            return str_repeat('•', $length);
        }

        $firstPart = mb_substr($normalized, 0, 2);
        $lastPart = mb_substr($normalized, -2);
        $middle = str_repeat('•', max($length - 4, 3));

        return "{$firstPart}{$middle}{$lastPart}";
    }
}
