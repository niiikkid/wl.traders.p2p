<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BalanceType;
use App\Enums\InvoiceType;
use App\Exceptions\InvoiceException;
use App\Exports\AdminWalletTransactionsExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\User\Wallet\DepositRequest;
use App\Http\Requests\Admin\User\Wallet\WithdrawRequest;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\TransactionResource;
use App\Http\Resources\UserResource;
use App\Http\Resources\WalletDepositInvoiceResource;
use App\Http\Resources\WithdrawalAddressResource;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletDepositInvoice;
use App\Services\Money\Currency;
use App\Services\Money\Money;
use App\Services\User\TeamLeaderInsuranceService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class UserWalletController extends Controller
{
    public function __construct(
        private readonly TeamLeaderInsuranceService $teamLeaderInsuranceService,
    ) {}

    public function index(User $user)
    {
        $user->loadMissing(['roles', 'wallet', 'teamLeader']);

        $wallet = $user->wallet;

        $walletAdminFullView = $user->hasRole('Super Admin');
        $walletSurfaces = $this->walletSurfacesForUser($user);

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

        $currentTab = request()->input('tab', 'invoices');
        if (empty($tabs[$currentTab])) {
            $currentTab = 'invoices';
        }

        if ($user->hasRole('Merchant')) {
            return $this->merchantUserIndex($user, $tabs, $currentTab, $walletSurfaces);
        }

        if ($walletAdminFullView) {
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
                    'balanceTypes' => [
                        'all' => [
                            'key' => 'all',
                            'name' => 'Тип кошелька',
                        ],
                        BalanceType::TRUST->value => [
                            'key' => BalanceType::TRUST->value,
                            'name' => 'Траст',
                        ],
                        BalanceType::MERCHANT->value => [
                            'key' => BalanceType::MERCHANT->value,
                            'name' => 'Мерчант',
                        ],
                        BalanceType::TEAMLEADER->value => [
                            'key' => BalanceType::TEAMLEADER->value,
                            'name' => 'Тимлид',
                        ],
                        BalanceType::PROVIDER->value => [
                            'key' => BalanceType::PROVIDER->value,
                            'name' => 'Провайдер',
                        ],
                        BalanceType::AGENT->value => [
                            'key' => BalanceType::AGENT->value,
                            'name' => 'Агент',
                        ],
                        BalanceType::RESERVE->value => [
                            'key' => BalanceType::RESERVE->value,
                            'name' => 'Резерв',
                        ],
                    ],
                ],
                'transactions' => [
                    'balanceTypes' => [
                        'all' => [
                            'key' => 'all',
                            'name' => 'Тип кошелька',
                        ],
                        BalanceType::TRUST->value => [
                            'key' => BalanceType::TRUST->value,
                            'name' => 'Траст',
                        ],
                        BalanceType::MERCHANT->value => [
                            'key' => BalanceType::MERCHANT->value,
                            'name' => 'Мерчант',
                        ],
                        BalanceType::TEAMLEADER->value => [
                            'key' => BalanceType::TEAMLEADER->value,
                            'name' => 'Тимлид',
                        ],
                        BalanceType::PROVIDER->value => [
                            'key' => BalanceType::PROVIDER->value,
                            'name' => 'Провайдер',
                        ],
                        BalanceType::AGENT->value => [
                            'key' => BalanceType::AGENT->value,
                            'name' => 'Агент',
                        ],
                        BalanceType::RESERVE->value => [
                            'key' => BalanceType::RESERVE->value,
                            'name' => 'Резерв',
                        ],
                    ],
                ],
            ];

            $currentFilters = [
                'invoices' => [
                    'invoiceTypes' => request()->input('currentFilters.invoices.invoiceTypes', 'all'),
                    'balanceTypes' => request()->input('currentFilters.invoices.balanceTypes', 'all'),
                ],
                'transactions' => [
                    'balanceTypes' => request()->input('currentFilters.transactions.balanceTypes', 'all'),
                ],
            ];

            $invoiceBalanceFilter = $currentFilters['invoices']['balanceTypes'];
            $transactionBalanceFilter = $currentFilters['transactions']['balanceTypes'];

            $invoicesBalanceType = $invoiceBalanceFilter === 'all'
                ? null
                : BalanceType::tryFrom($invoiceBalanceFilter);
            $transactionsBalanceType = $transactionBalanceFilter === 'all'
                ? null
                : BalanceType::tryFrom($transactionBalanceFilter);
        } else {
            $teamLeaderUsesSharedReserve = $user->hasRole('Team Leader')
                && $this->teamLeaderInsuranceService->teamLeaderUsesSharedReserve($user);

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

                $invoiceBalanceFilter = $currentFilters['invoices']['balanceTypes'];
                $transactionBalanceFilter = $currentFilters['transactions']['balanceTypes'];
                $invoicesBalanceType = $this->teamLeaderInsuranceService->resolveSharedReserveHistoryBalanceType($invoiceBalanceFilter);
                $transactionsBalanceType = $this->teamLeaderInsuranceService->resolveSharedReserveHistoryBalanceType($transactionBalanceFilter);
            } else {
                $scopedBalanceType = $this->resolveScopedBalanceType($user);
                $historyBalanceType = $this->resolveHistoryBalanceType($user, $scopedBalanceType);
                $invoicesBalanceType = $historyBalanceType;
                $transactionsBalanceType = $historyBalanceType;
            }
        }

        $walletStats = services()->wallet()->getWalletStats($wallet)->toArray();

        $invoices = null;
        $transactions = null;

        if ($currentTab === 'invoices') {
            $invoices = queries()->invoice()->paginate(
                wallet: $wallet,
                invoiceType: InvoiceType::tryFrom($currentFilters['invoices']['invoiceTypes']),
                balanceType: $invoicesBalanceType,
            );
            $invoices = InvoiceResource::collection($invoices);
        } elseif ($currentTab === 'transactions') {
            $transactions = queries()->transaction()->paginate(
                wallet: $wallet,
                balanceType: $transactionsBalanceType,
            );
            $transactions = TransactionResource::collection($transactions);
        }

        $teamLeaderInsurance = $this->teamLeaderInsuranceService->teamLeaderInsurancePropsForUser($user);
        $walletHistoryShowsBalanceType = $walletAdminFullView
            || ($user->hasRole('Team Leader') && $this->teamLeaderInsuranceService->teamLeaderUsesSharedReserve($user));
        $withdrawalAddresses = $this->withdrawalAddressProps($user);
        $walletDepositInvoices = $this->walletDepositInvoiceProps($wallet);

        $user = UserResource::make($user)->resolve();

        return Inertia::render('Wallet/Index', compact(
            'walletStats',
            'invoices',
            'transactions',
            'user',
            'tabs',
            'filters',
            'currentTab',
            'currentFilters',
            'walletSurfaces',
            'walletAdminFullView',
            'teamLeaderInsurance',
            'walletHistoryShowsBalanceType',
            'withdrawalAddresses',
            'walletDepositInvoices',
        ));
    }

    public function exportTransactions(Request $request, User $user): BinaryFileResponse
    {
        $user->loadMissing(['wallet', 'roles']);

        if (! $user->wallet) {
            abort(404);
        }

        $balanceType = $user->hasRole('Super Admin')
            ? $this->resolveExportBalanceTypeForSuperAdmin($request)
            : $this->resolveScopedBalanceType($user);

        $filename = now()->format('Y-m-d_H-i-s').'_wallet-transactions-user-'.$user->id.'.xlsx';

        return Excel::download(
            new AdminWalletTransactionsExport($user->wallet, $balanceType),
            $filename
        );
    }

    private function merchantUserIndex(User $user, array $tabs, string $currentTab, array $walletSurfaces): Response
    {
        $merchantWallets = services()->wallet()->getMerchantWalletSummaries($user);
        $merchantFilterVariants = $this->merchantFilterVariants($merchantWallets);

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

        $currentFilters = [
            'invoices' => [
                'invoiceTypes' => request()->input('currentFilters.invoices.invoiceTypes', 'all'),
                'merchants' => request()->input('currentFilters.invoices.merchants', 'all'),
            ],
            'transactions' => [
                'merchants' => request()->input('currentFilters.transactions.merchants', 'all'),
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

        $teamLeaderInsurance = null;
        $walletHistoryShowsBalanceType = false;
        $withdrawalAddresses = $this->withdrawalAddressProps($user);
        $walletDepositInvoices = $this->walletDepositInvoicePropsForWalletIds($walletIds);
        $merchantWalletMode = true;
        $walletAdminFullView = false;

        $user = UserResource::make($user)->resolve();

        return Inertia::render('Wallet/Index', compact(
            'walletStats',
            'invoices',
            'transactions',
            'user',
            'tabs',
            'filters',
            'currentTab',
            'currentFilters',
            'walletSurfaces',
            'walletAdminFullView',
            'teamLeaderInsurance',
            'walletHistoryShowsBalanceType',
            'withdrawalAddresses',
            'walletDepositInvoices',
            'merchantWallets',
            'merchantWalletMode',
            'selectedMerchantId',
        ));
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
     * Для кошелька Super Admin выгрузка только по явно выбранному типу кошелька (не «все»).
     */
    private function resolveExportBalanceTypeForSuperAdmin(Request $request): BalanceType
    {
        $raw = $request->query('balance_type');

        if ($raw === null || $raw === '' || $raw === 'all') {
            abort(422, 'Выберите тип кошелька для выгрузки.');
        }

        $balanceType = BalanceType::tryFrom((string) $raw);

        if ($balanceType === null) {
            abort(422, 'Недопустимый тип кошелька.');
        }

        return $balanceType;
    }

    /**
     * Карточки балансов на странице «как у пользователя с этой ролью», кроме Super Admin (ему — все сразу).
     *
     * @return array{trust: bool, merchant: bool, teamleader: bool, reserve: bool, provider: bool, agent: bool, escrow: bool, dispute: bool}
     */
    private function walletSurfacesForUser(User $user): array
    {
        if ($user->hasRole('Super Admin')) {
            return [
                'trust' => true,
                'merchant' => true,
                'teamleader' => true,
                'reserve' => true,
                'provider' => true,
                'agent' => true,
                'escrow' => true,
                'dispute' => true,
            ];
        }

        if ($user->hasRole('Merchant')) {
            return [
                'trust' => false,
                'merchant' => true,
                'teamleader' => false,
                'reserve' => false,
                'provider' => false,
                'agent' => false,
                'escrow' => false,
                'dispute' => false,
            ];
        }

        if ($user->hasRole('Trader')) {
            return [
                'trust' => true,
                'merchant' => false,
                'teamleader' => false,
                'reserve' => false,
                'provider' => false,
                'agent' => false,
                'escrow' => true,
                'dispute' => true,
            ];
        }

        if ($user->hasRole('Team Leader')) {
            $sharedReserve = $this->teamLeaderInsuranceService->teamLeaderUsesSharedReserve($user);

            return [
                'trust' => false,
                'merchant' => false,
                'teamleader' => true,
                'reserve' => $sharedReserve,
                'provider' => false,
                'agent' => false,
                'escrow' => false,
                'dispute' => false,
            ];
        }

        if ($user->hasRole('Support')) {
            return [
                'trust' => true,
                'merchant' => false,
                'teamleader' => false,
                'reserve' => false,
                'provider' => false,
                'agent' => false,
                'escrow' => true,
                'dispute' => true,
            ];
        }

        return [
            'trust' => true,
            'merchant' => false,
            'teamleader' => false,
            'reserve' => false,
            'provider' => false,
            'agent' => false,
            'escrow' => true,
            'dispute' => true,
        ];
    }

    /**
     * Один тип баланса для истории инвойсов/транзакций (как на wallet.index / merchant.finances / leader.finances).
     */
    private function resolveScopedBalanceType(User $user): BalanceType
    {
        if ($user->hasRole('Merchant')) {
            return BalanceType::MERCHANT;
        }
        if ($user->hasRole('Trader')) {
            return BalanceType::TRUST;
        }
        if ($user->hasRole('Team Leader')) {
            return BalanceType::TEAMLEADER;
        }
        if ($user->hasRole('Support')) {
            return BalanceType::TRUST;
        }

        return BalanceType::TRUST;
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

    private function resolveHistoryBalanceType(User $user, BalanceType $scopedBalanceType): ?BalanceType
    {
        return $scopedBalanceType;
    }

    public function deposit(DepositRequest $request, User $user)
    {
        try {
            $balanceType = BalanceType::from($request->balance_type);
            $wallet = $this->resolveWalletForAdminBalanceOperation($request, $user, $balanceType);

            services()->invoice()->deposit(
                walletID: $wallet->id,
                amount: Money::fromPrecision($request->amount, Currency::USDT()),
                balanceType: $balanceType,
                transactionID: null,
                txHash: $request->tx_hash
            );

            return redirect()->back();
        } catch (InvoiceException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function withdraw(WithdrawRequest $request, User $user)
    {
        try {
            $balanceType = BalanceType::from($request->balance_type);
            $wallet = $this->resolveWalletForAdminBalanceOperation($request, $user, $balanceType);

            services()->invoice()->withdraw(
                walletID: $wallet->id,
                amount: Money::fromPrecision($request->amount, Currency::USDT()),
                balanceType: $balanceType
            );

            return redirect()->back();
        } catch (InvoiceException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    private function resolveWalletForAdminBalanceOperation(Request $request, User $user, BalanceType $balanceType): Wallet
    {
        if ($user->hasRole('Merchant') && $balanceType->equals(BalanceType::MERCHANT)) {
            $wallet = Wallet::query()
                ->where('user_id', $user->id)
                ->where('merchant_id', $request->integer('merchant_id'))
                ->first();

            if ($wallet === null) {
                throw InvoiceException::merchantWalletMissing();
            }

            return $wallet;
        }

        return $user->wallet;
    }

    /**
     * @return array{items: array<int, mixed>, has_2fa: bool}
     */
    private function withdrawalAddressProps(User $user): array
    {
        $addresses = $user->withdrawalAddresses()
            ->withCount(['invoices as withdrawals_count' => function ($query): void {
                $query->where('type', InvoiceType::WITHDRAWAL);
            }])
            ->withSum(['invoices as withdrawals_amount' => function ($query): void {
                $query->where('type', InvoiceType::WITHDRAWAL);
            }], 'amount')
            ->latest()
            ->get();

        return [
            'items' => WithdrawalAddressResource::collection($addresses)->resolve(),
            'has_2fa' => $user->google2fa_secret !== null,
        ];
    }
}
