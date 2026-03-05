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
        $wallet = $user->wallet;

        $tabs = [
            'invoices' => [
                'key' => 'invoices',
                'name' => 'Инвойсы',
            ],
            'transactions' => [
                'key' => 'transactions',
                'name' => 'Транзакции',
            ]
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
                ],
            ]
        ];

        $currentTab = request()->input('tab', 'invoices');
        if (empty($tabs[$currentTab])) {
            $currentTab = 'invoices';
        }

        $currentFilters = [
            'invoices' => [
                'invoiceTypes' => request()->input('currentFilters.invoices.invoiceTypes', 'all'),
                'balanceTypes' => request()->input('currentFilters.invoices.balanceTypes', 'all'),
            ],
            'transactions' => [
                'balanceTypes' => request()->input('currentFilters.transactions.balanceTypes', 'all'),
            ]
        ];

        $walletStats = services()->wallet()->getWalletStats($wallet)->toArray();

        $invoices = null;
        $transactions = null;

        if ($currentTab === 'invoices') {
            $invoices = queries()->invoice()->paginate(
                wallet: $wallet,
                invoiceType: InvoiceType::tryFrom($currentFilters['invoices']['invoiceTypes']),
                balanceType: BalanceType::tryFrom($currentFilters['invoices']['balanceTypes']),
            );
            $invoices = InvoiceResource::collection($invoices);
        } else if ($currentTab === 'transactions') {
            $transactions = queries()->transaction()->paginate(
                wallet: $wallet,
                balanceType: BalanceType::tryFrom($currentFilters['transactions']['balanceTypes']),
            );
            $transactions = TransactionResource::collection($transactions);
        }

        $user = UserResource::make($user)->resolve();

        return Inertia::render('Wallet/Index', compact('walletStats', 'invoices', 'transactions', 'user', 'tabs', 'filters', 'currentTab', 'currentFilters'));
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
