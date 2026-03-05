<?php

namespace App\Http\Controllers\Merchant;

use App\DTO\Payout\PayoutCreateDTO;
use App\Enums\PayoutMethodType;
use App\Exceptions\PayoutException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payout\StoreRequest;
use App\Http\Resources\Payout\MerchantPayoutResource;
use App\Http\Resources\PaymentGatewayResource;
use App\Models\Merchant;
use App\Models\PaymentGateway;
use App\Services\Money\Currency;
use App\Services\Money\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PayoutController extends Controller
{
    public function index(Request $request): Response
    {
        if (! $request->user()->payouts_enabled) {
            abort(403, 'Выплаты для вашего аккаунта отключены.');
        }

        $filters = $this->getTableFilters();
        $filtersVariants = $this->getFiltersData();

        $payouts = queries()->payout()->paginateForMerchant($request->user(), $filters);
        $payouts = MerchantPayoutResource::collection($payouts);

        return Inertia::render('Payout/Merchant/Index', [
            'payouts' => $payouts,
            'filters' => $filters,
            'filtersVariants' => $filtersVariants,
        ]);
    }

    public function createData(Request $request): JsonResponse
    {
        $this->ensurePayoutsEnabled($request);

        $paymentGatewayModels = PaymentGateway::query()
            ->active()
            ->where('is_payouts_enabled', true)
            ->orderByDesc('id')
            ->get();

        $paymentGateways = PaymentGatewayResource::collection($paymentGatewayModels)->resolve();

        $merchantModels = Merchant::query()
            ->where('user_id', $request->user()->id)
            ->whereNotNull('validated_at')
            ->whereNull('banned_at')
            ->where('active', true)
            ->orderByDesc('id')
            ->get();

        $merchants = $merchantModels
            ->map(function (Merchant $merchant) {
                return [
                    'id' => $merchant->id,
                    'name' => $merchant->name,
                    'payout_callback_url' => $merchant->payout_callback_url,
                ];
            })
            ->values();

        $selectedMerchantId = (int) $request->input('merchant_id');
        $selectedMerchant = $selectedMerchantId
            ? $merchantModels->firstWhere('id', $selectedMerchantId)
            : $merchantModels->first();

        $selectedGatewayCode = $request->input('payment_gateway');
        $selectedGateway = $selectedGatewayCode
            ? $paymentGatewayModels->firstWhere('code', $selectedGatewayCode)
            : null;

        $selectedCurrency = $request->input('currency');
        $rate = $this->resolveRateData($selectedMerchant, $selectedGateway, $selectedCurrency);

        return response()->json([
            'success' => true,
            'data' => [
                'paymentGateways' => $paymentGateways,
                'currencies' => Currency::getAll()->map(fn (Currency $currency) => [
                    'code' => strtoupper($currency->getCode()),
                    'name' => $currency->getName(),
                ])->values(),
                'merchants' => $merchants,
                'payoutMethodTypes' => [
                    ['id' => PayoutMethodType::SBP->value, 'name' => 'СБП'],
                    ['id' => PayoutMethodType::CARD->value, 'name' => 'Карта'],
                ],
                'rate' => $rate,
            ],
        ]);
    }

    public function store(StoreRequest $request): JsonResponse
    {
        $this->ensurePayoutsEnabled($request);

        $merchant = Merchant::query()->findOrFail($request->validated('merchant_id'));

        Gate::authorize('access-to-merchant', $merchant);

        $paymentGateway = null;
        $gatewayCode = $request->validated('payment_gateway');
        if ($gatewayCode) {
            $paymentGateway = PaymentGateway::query()
                ->where('code', $gatewayCode)
                ->where('is_payouts_enabled', true)
                ->active()
                ->firstOrFail();
        }

        $currencyCode = $paymentGateway
            ? strtoupper($paymentGateway->currency->getCode())
            : strtoupper($request->validated('currency'));

        $dto = PayoutCreateDTO::make(
            merchant: $merchant,
            paymentGateway: $paymentGateway,
            externalId: $request->validated('external_id'),
            amountFiat: Money::fromPrecision($request->validated('amount'), $currencyCode),
            methodType: PayoutMethodType::from($request->validated('payout_method_type')),
            requisites: $request->validated('requisites'),
            initials: $request->validated('initials'),
            currencyCode: $currencyCode,
            callbackUrl: $request->validated('callback_url'),
            bankName: $request->validated('bank_name'),
        );

        try {
            $payout = services()->payout()->create($dto);
        } catch (PayoutException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => MerchantPayoutResource::make($payout),
        ]);
    }

    private function ensurePayoutsEnabled(Request $request): void
    {
        if (! $request->user()->payouts_enabled) {
            abort(403, 'Выплаты для вашего аккаунта отключены.');
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveRateData(?Merchant $merchant, ?PaymentGateway $paymentGateway, ?string $currencyCode): ?array
    {
        if (! $merchant) {
            return null;
        }

        $currency = $paymentGateway?->currency;

        if (! $currency && $currencyCode) {
            try {
                $currency = Currency::make($currencyCode);
            } catch (\Throwable) {
                return null;
            }
        }

        if (! $currency) {
            return null;
        }

        $market = $merchant->getGeoMarket($currency);
        if (! $market) {
            return null;
        }

        $supportsCurrency = services()->market()
            ->getSupportedCurrencies($market)
            ->contains(fn (Currency $supported) => $supported->getCode() === $currency->getCode());

        if (! $supportsCurrency) {
            return null;
        }

        $conversionPrice = services()->market()->getBuyPrice($currency, $market, false);

        if (! $conversionPrice->greaterThanZero()) {
            return null;
        }

        return [
            'market' => $market->value,
            'price' => $conversionPrice->toBeauty(),
            'currency' => strtoupper($conversionPrice->getCurrency()->getCode()),
            'fixed_at' => now()->toIso8601String(),
        ];
    }
}

