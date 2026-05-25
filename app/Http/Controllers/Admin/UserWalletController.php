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
use App\Models\User;
use App\Services\Money\Currency;
use App\Services\Money\Money;
use App\Services\User\TeamLeaderInsuranceService;
use Illuminate\Http\Request;
use Inertia\Inertia;
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

        if ($user->hasRole('Provider Liquidity')) {
            return [
                'trust' => false,
                'merchant' => false,
                'teamleader' => false,
                'reserve' => false,
                'provider' => true,
                'agent' => false,
                'escrow' => false,
                'dispute' => false,
            ];
        }

        if ($user->hasRole('Agent')) {
            return [
                'trust' => false,
                'merchant' => false,
                'teamleader' => false,
                'reserve' => false,
                'provider' => false,
                'agent' => true,
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
        if ($user->hasRole('Provider Liquidity')) {
            return BalanceType::PROVIDER;
        }
        if ($user->hasRole('Agent')) {
            return BalanceType::AGENT;
        }
        if ($user->hasRole('Support')) {
            return BalanceType::TRUST;
        }

        return BalanceType::TRUST;
    }

    private function resolveHistoryBalanceType(User $user, BalanceType $scopedBalanceType): ?BalanceType
    {
        return $scopedBalanceType;
    }

    public function deposit(DepositRequest $request, User $user)
    {
        try {
            services()->invoice()->deposit(
                walletID: $user->wallet->id,
                amount: Money::fromPrecision($request->amount, Currency::USDT()),
                balanceType: BalanceType::from($request->balance_type),
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
            services()->invoice()->withdraw(
                walletID: $user->wallet->id,
                amount: Money::fromPrecision($request->amount, Currency::USDT()),
                balanceType: BalanceType::from($request->balance_type)
            );

            return redirect()->back();
        } catch (InvoiceException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
