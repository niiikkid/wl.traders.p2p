<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BalanceType;
use App\Enums\InvoiceType;
use App\Exceptions\InvoiceException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\User\Wallet\DepositRequest;
use App\Http\Requests\Admin\User\Wallet\WithdrawRequest;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\TransactionResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Money\Currency;
use App\Services\Money\Money;
use Inertia\Inertia;

class UserWalletController extends Controller
{
    public function index(User $user)
    {
        $user->loadMissing(['roles', 'wallet']);

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

            $scopedBalanceType = $this->resolveScopedBalanceType($user);
            $invoicesBalanceType = $scopedBalanceType;
            $transactionsBalanceType = $scopedBalanceType;
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
        ));
    }

    /**
     * Карточки балансов на странице «как у пользователя с этой ролью», кроме Super Admin (ему — все сразу).
     *
     * @return array{trust: bool, merchant: bool, teamleader: bool, escrow: bool, dispute: bool}
     */
    private function walletSurfacesForUser(User $user): array
    {
        if ($user->hasRole('Super Admin')) {
            return [
                'trust' => true,
                'merchant' => true,
                'teamleader' => true,
                'escrow' => true,
                'dispute' => true,
            ];
        }

        if ($user->hasRole('Merchant')) {
            return [
                'trust' => false,
                'merchant' => true,
                'teamleader' => false,
                'escrow' => false,
                'dispute' => false,
            ];
        }

        if ($user->hasRole('Trader')) {
            return [
                'trust' => true,
                'merchant' => false,
                'teamleader' => false,
                'escrow' => true,
                'dispute' => true,
            ];
        }

        if ($user->hasRole('Team Leader')) {
            return [
                'trust' => false,
                'merchant' => false,
                'teamleader' => true,
                'escrow' => false,
                'dispute' => false,
            ];
        }

        if ($user->hasRole('Support')) {
            return [
                'trust' => true,
                'merchant' => false,
                'teamleader' => false,
                'escrow' => true,
                'dispute' => true,
            ];
        }

        if ($user->hasRole('Merchant Support')) {
            return [
                'trust' => false,
                'merchant' => true,
                'teamleader' => false,
                'escrow' => false,
                'dispute' => false,
            ];
        }

        return [
            'trust' => true,
            'merchant' => false,
            'teamleader' => false,
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
        if ($user->hasRole('Merchant Support')) {
            return BalanceType::MERCHANT;
        }

        return BalanceType::TRUST;
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
