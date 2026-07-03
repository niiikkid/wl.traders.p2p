<?php

namespace App\Http\Controllers;

use App\Enums\BalanceType;
use App\Enums\InvoiceType;
use App\Http\Requests\Wallet\UpdateFiatCurrencyRequest;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\TransactionResource;
use App\Http\Resources\WalletDepositInvoiceResource;
use App\Http\Resources\WithdrawalAddressResource;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletDepositInvoice;
use App\Services\User\TeamLeaderInsuranceService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WalletController extends Controller
{
    public function __construct(
        private readonly TeamLeaderInsuranceService $teamLeaderInsuranceService,
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();

        if ($request->routeIs('merchant.finances.index') && $user instanceof User) {
            return $this->merchantIndex($request, $user);
        }

        $teamLeaderUsesSharedReserve = $request->routeIs('leader.finances.index')
            && $user instanceof User
            && $this->teamLeaderInsuranceService->teamLeaderUsesSharedReserve($user);

        /**
         * @var Wallet $wallet
         */
        $wallet = $request->user()->wallet;

        $tabs = [
            'invoices' => [
                'key' => 'invoices',
                'name' => 'Инвойсы',
            ],
            'transactions' => [
                'key' => 'transactions',
                'name' => 'Транзакции',
            ],
        ];

        $filters = [
            'invoices' => [
                'invoiceTypes' => [
                    'all' => [
                        'key' => 'all',
                        'name' => 'Тип инвойса',
                    ],
                    InvoiceType::DEPOSIT->value => [
                        'key' => InvoiceType::DEPOSIT->value,
                        'name' => 'Пополнение',
                    ],
                    InvoiceType::WITHDRAWAL->value => [
                        'key' => InvoiceType::WITHDRAWAL->value,
                        'name' => 'Вывод',
                    ],
                ],
            ],
        ];

        $currentTab = request()->input('tab', 'invoices');
        if (empty($tabs[$currentTab])) {
            $currentTab = 'invoices';
        }

        $currentFilters = [
            'invoices' => [
                'invoiceTypes' => request()->input('currentFilters.invoices.invoiceTypes', 'all'),
            ],
        ];

        if ($teamLeaderUsesSharedReserve) {
            $balanceFilterVariants = $this->teamLeaderInsuranceService->sharedReserveHistoryBalanceFilterVariants();
            $filters['invoices']['balanceTypes'] = $balanceFilterVariants;
            $filters['transactions']['balanceTypes'] = $balanceFilterVariants;
            $currentFilters['invoices']['balanceTypes'] = request()->input('currentFilters.invoices.balanceTypes', 'all');
            $currentFilters['transactions']['balanceTypes'] = request()->input('currentFilters.transactions.balanceTypes', 'all');
        }

        $walletStats = services()->wallet()->getWalletStats($wallet)->toArray();

        $invoices = null;
        $transactions = null;

        if ($currentTab === 'invoices') {
            $invoices = queries()->invoice()->paginate(
                wallet: $wallet,
                invoiceType: InvoiceType::tryFrom($currentFilters['invoices']['invoiceTypes']),
                balanceType: $this->resolvePaginatedHistoryBalanceType($request, $teamLeaderUsesSharedReserve, $currentFilters['invoices']['balanceTypes'] ?? 'all'),
            );
            $invoices = InvoiceResource::collection($invoices);
        } elseif ($currentTab === 'transactions') {
            $transactions = queries()->transaction()->paginate(
                wallet: $wallet,
                balanceType: $this->resolvePaginatedHistoryBalanceType($request, $teamLeaderUsesSharedReserve, $currentFilters['transactions']['balanceTypes'] ?? 'all'),
            );
            $transactions = TransactionResource::collection($transactions);
        }

        $walletSurfaces = null;

        $traderBalanceTransfer = $this->traderBalanceTransferProps($request, $wallet);
        $teamLeaderInsurance = $this->teamLeaderInsuranceProps($request);
        $walletHistoryShowsBalanceType = $teamLeaderUsesSharedReserve;
        $withdrawalAddresses = $this->withdrawalAddressProps($user);
        $walletDepositInvoices = $this->walletDepositInvoiceProps($wallet);

        return Inertia::render('Wallet/Index', compact(
            'walletStats',
            'invoices',
            'transactions',
            'tabs',
            'filters',
            'currentTab',
            'currentFilters',
            'walletSurfaces',
            'traderBalanceTransfer',
            'teamLeaderInsurance',
            'walletHistoryShowsBalanceType',
            'withdrawalAddresses',
            'walletDepositInvoices',
        ));
    }

    private function merchantIndex(Request $request, User $user): Response
    {
        $merchantWallets = services()->wallet()->getMerchantWalletSummaries($user);
        $merchantFilterVariants = $this->merchantFilterVariants($merchantWallets);

        $tabs = [
            'invoices' => [
                'key' => 'invoices',
                'name' => 'Инвойсы',
            ],
            'transactions' => [
                'key' => 'transactions',
                'name' => 'Транзакции',
            ],
        ];

        $filters = [
            'invoices' => [
                'invoiceTypes' => [
                    'all' => [
                        'key' => 'all',
                        'name' => 'Тип инвойса',
                    ],
                    InvoiceType::DEPOSIT->value => [
                        'key' => InvoiceType::DEPOSIT->value,
                        'name' => 'Пополнение',
                    ],
                    InvoiceType::WITHDRAWAL->value => [
                        'key' => InvoiceType::WITHDRAWAL->value,
                        'name' => 'Вывод',
                    ],
                ],
                'merchants' => $merchantFilterVariants,
            ],
            'transactions' => [
                'merchants' => $merchantFilterVariants,
            ],
        ];

        $currentTab = $request->input('tab', 'invoices');
        if (empty($tabs[$currentTab])) {
            $currentTab = 'invoices';
        }

        $currentFilters = [
            'invoices' => [
                'invoiceTypes' => $request->input('currentFilters.invoices.invoiceTypes', 'all'),
                'merchants' => $request->input('currentFilters.invoices.merchants', 'all'),
            ],
            'transactions' => [
                'merchants' => $request->input('currentFilters.transactions.merchants', 'all'),
            ],
        ];

        $merchantFilterKey = $currentTab === 'transactions'
            ? $currentFilters['transactions']['merchants']
            : $currentFilters['invoices']['merchants'];
        $selectedMerchantId = $this->resolveMerchantFilterId($merchantFilterKey);
        $walletIds = $this->merchantWalletIds($user, $selectedMerchantId);

        $walletStats = services()->wallet()->getMerchantWalletStats($user, $selectedMerchantId);
        $invoices = null;
        $transactions = null;

        if ($currentTab === 'invoices') {
            $invoices = queries()->invoice()->paginateForWalletIds(
                walletIds: $walletIds,
                invoiceType: InvoiceType::tryFrom($currentFilters['invoices']['invoiceTypes']),
                balanceType: BalanceType::MERCHANT,
            );
            $invoices = InvoiceResource::collection($invoices);
        } elseif ($currentTab === 'transactions') {
            $transactions = queries()->transaction()->paginateForWalletIds(
                walletIds: $walletIds,
                balanceType: BalanceType::MERCHANT,
            );
            $transactions = TransactionResource::collection($transactions);
        }

        $walletSurfaces = [
            'trust' => false,
            'merchant' => true,
            'teamleader' => false,
            'reserve' => false,
            'provider' => false,
            'agent' => false,
            'escrow' => false,
            'dispute' => false,
        ];
        $traderBalanceTransfer = null;
        $teamLeaderInsurance = null;
        $walletHistoryShowsBalanceType = false;
        $withdrawalAddresses = $this->withdrawalAddressProps($user);
        $walletDepositInvoices = $this->walletDepositInvoicePropsForWalletIds($walletIds);
        $merchantWalletMode = true;

        return Inertia::render('Wallet/Index', compact(
            'walletStats',
            'invoices',
            'transactions',
            'tabs',
            'filters',
            'currentTab',
            'currentFilters',
            'walletSurfaces',
            'traderBalanceTransfer',
            'teamLeaderInsurance',
            'walletHistoryShowsBalanceType',
            'withdrawalAddresses',
            'walletDepositInvoices',
            'merchantWallets',
            'merchantWalletMode',
            'selectedMerchantId',
        ));
    }

    public function updateFiatCurrency(UpdateFiatCurrencyRequest $request)
    {
        $request->user()->update([
            'fiat_currency' => strtolower($request->validated('fiat_currency')),
        ]);

        return redirect()->back();
    }

    /**
     * @param  array<int, array<string, mixed>>  $merchantWallets
     * @return array<string, array{key: string, name: string}>
     */
    private function merchantFilterVariants(array $merchantWallets): array
    {
        $variants = [
            'all' => [
                'key' => 'all',
                'name' => 'Все магазины',
            ],
        ];

        foreach ($merchantWallets as $merchantWallet) {
            $variants[(string) $merchantWallet['id']] = [
                'key' => (string) $merchantWallet['id'],
                'name' => (string) $merchantWallet['name'],
            ];
        }

        return $variants;
    }

    private function resolveMerchantFilterId(?string $merchantFilterKey): ?int
    {
        if ($merchantFilterKey === null || $merchantFilterKey === '' || $merchantFilterKey === 'all') {
            return null;
        }

        return (int) $merchantFilterKey;
    }

    /**
     * @return array<int, int>
     */
    private function merchantWalletIds(User $user, ?int $merchantId = null): array
    {
        return Wallet::query()
            ->where('user_id', $user->id)
            ->whereNotNull('merchant_id')
            ->when($merchantId !== null, function ($query) use ($merchantId): void {
                $query->where('merchant_id', $merchantId);
            })
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * Trader's own «Финансы» page (not Team Leader read-only view of a trader).
     *
     * @see wiki/trader-balance-transfers/trader-balance-transfer-implementation-plan.md
     */
    private function isTraderOwnFinancesPage(Request $request): bool
    {
        return $request->routeIs('wallet.index')
            && $request->user()?->hasRole('Trader');
    }

    /**
     * @return array{available: bool, trust_balance: string, has_2fa: bool}|null
     */
    private function traderBalanceTransferProps(Request $request, Wallet $wallet): ?array
    {
        if (! $this->isTraderOwnFinancesPage($request)) {
            return null;
        }

        $user = $request->user();

        if (! $user instanceof User) {
            return null;
        }

        return [
            'available' => $this->canTraderBalanceTransfer($user),
            'trust_balance' => $wallet->trust_balance->toPrecision(),
            'has_2fa' => $user->google2fa_secret !== null,
        ];
    }

    private function canTraderBalanceTransfer(User $user): bool
    {
        if (! $user->hasRole('Trader')) {
            return false;
        }

        if ($user->team_leader_id === null) {
            return false;
        }

        return $user->archived_at === null && $user->banned_at === null;
    }

    private function resolveBalanceType(Request $request): BalanceType
    {
        return match ($request->route()->getName()) {
            'wallet.index' => BalanceType::TRUST,
            'merchant.finances.index' => BalanceType::MERCHANT,
            'leader.finances.index' => BalanceType::TEAMLEADER,
        };
    }

    private function resolvePaginatedHistoryBalanceType(
        Request $request,
        bool $teamLeaderUsesSharedReserve,
        string $balanceFilterKey,
    ): ?BalanceType {
        if ($teamLeaderUsesSharedReserve) {
            return $this->teamLeaderInsuranceService->resolveSharedReserveHistoryBalanceType($balanceFilterKey);
        }

        return $this->resolveBalanceType($request);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function teamLeaderInsuranceProps(Request $request): ?array
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return null;
        }

        return $this->teamLeaderInsuranceService->teamLeaderInsurancePropsForUser($user);
    }

    /**
     * @return array{items: array<int, mixed>, has_2fa: bool}
     */
    private function withdrawalAddressProps(User $user): array
    {
        $addresses = $user->withdrawalAddresses()
            ->latest()
            ->get();

        return [
            'items' => WithdrawalAddressResource::collection($addresses)->resolve(),
            'has_2fa' => $user->google2fa_secret !== null,
        ];
    }

    /**
     * @return array<int, mixed>
     */
    private function walletDepositInvoiceProps(Wallet $wallet): array
    {
        $invoices = WalletDepositInvoice::query()
            ->with('wallet.merchant')
            ->where('wallet_id', $wallet->id)
            ->latest()
            ->limit(20)
            ->get();

        return WalletDepositInvoiceResource::collection($invoices)->resolve();
    }

    /**
     * @param  array<int, int>  $walletIds
     * @return array<int, mixed>
     */
    private function walletDepositInvoicePropsForWalletIds(array $walletIds): array
    {
        if ($walletIds === []) {
            return [];
        }

        $invoices = WalletDepositInvoice::query()
            ->with('wallet.merchant')
            ->whereIn('wallet_id', $walletIds)
            ->latest()
            ->limit(20)
            ->get();

        return WalletDepositInvoiceResource::collection($invoices)->resolve();
    }
}
