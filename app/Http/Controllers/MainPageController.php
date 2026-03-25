<?php

namespace App\Http\Controllers;

use App\Contracts\MainPageCacheServiceContract;
use App\Contracts\MainPageStatsServiceContract;
use App\Enums\DetailType;
use App\Models\Merchant;
use App\Models\Order;
use App\Models\PaymentDetail;
use App\Models\PaymentGateway;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Illuminate\Support\Str;

class MainPageController extends Controller
{
    public function __construct(
        private readonly MainPageCacheServiceContract $mainPageCacheService,
        private readonly MainPageStatsServiceContract $mainPageStatsService,
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
        $walletStats = services()->wallet()->getWalletStats($user->wallet)->toArray();

        $tempVip = $user->getTempVipProgressData();

        return Inertia::render('MainPage/Trader/Index', [
            ...$stats,
            'walletStats' => $walletStats,
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
        $merchantId = request()->get('merchant_id');
        $periodPreset = (string) request()->get('period', 'all');
        $dateFrom = request()->get('date_from');
        $dateTo = request()->get('date_to');
        $filters = [
            'traderIds' => request()->input('trader_ids', []),
            'paymentMethodIds' => request()->input('payment_method_ids', []),
            'paymentDetailIds' => request()->input('payment_detail_ids', []),
            'merchantIds' => request()->input('merchant_ids', []),
        ];

        $stats = $this->mainPageStatsService->buildAdminStats(
            auth()->user(),
            $merchantId,
            $periodPreset,
            $dateFrom,
            $dateTo,
            $filters,
        );

        return Inertia::render('MainPage/Admin/Index', $stats);
    }

    public function adminFilterOptions(Request $request, string $type): JsonResponse
    {
        $search = trim((string) $request->get('query', ''));
        $selectedIds = collect($request->input('selected_ids', []))
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->toArray();

        $options = match ($type) {
            'trader' => $this->searchTraders($search, $selectedIds),
            'payment_method' => $this->searchPaymentMethods($search, $selectedIds),
            'payment_detail' => $this->searchPaymentDetails($search, $selectedIds),
            'merchant' => $this->searchMerchants($search, $selectedIds),
            default => collect(),
        };

        return response()->json($options->values());
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
                'label' => "{$gateway->name} " . strtoupper((string) $gateway->currency?->getCode()),
            ]),
            PaymentGateway::query()->whereIn('id', $selectedIds)->get()->map(fn (PaymentGateway $gateway) => [
                'value' => $gateway->id,
                'label' => "{$gateway->name} " . strtoupper((string) $gateway->currency?->getCode()),
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
            DetailType::CARD, DetailType::ACCOUNT_NUMBER, DetailType::IBAN_UAH =>
                '•••• ' . Str::of($rawDetail)->replaceMatches('/\D+/', '')->substr(-4),
            DetailType::PHONE, DetailType::MOBILE_COMMERCE =>
                $this->maskPhoneValue($rawDetail),
            default => Str::limit($rawDetail, 18, '...'),
        };

        return (string) $shortValue;
    }

    private function maskPhoneValue(string $value): string
    {
        $normalized = preg_replace('/\s+/', '', $value);
        if (!$normalized) {
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
