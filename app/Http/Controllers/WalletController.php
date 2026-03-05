<?php

namespace App\Http\Controllers;

use App\Enums\BalanceType;
use App\Enums\InvoiceType;
use App\Http\Requests\Wallet\UpdateFiatCurrencyRequest;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\TransactionResource;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WalletController extends Controller
{
    public function index(Request $request)
    {
        if ($request->route()->action['as'] === 'wallet.index') {
            $balanceType = BalanceType::TRUST;
        } else if ($request->route()->action['as'] === 'merchant.finances.index') {
            $balanceType = BalanceType::MERCHANT;
        } else if ($request->route()->action['as'] === 'leader.finances.index') {
            $balanceType = BalanceType::TEAMLEADER;
        }

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

        $walletStats = services()->wallet()->getWalletStats($wallet)->toArray();

        $invoices = null;
        $transactions = null;

        if ($currentTab === 'invoices') {
            $invoices = queries()->invoice()->paginate(
                wallet: $wallet,
                invoiceType: InvoiceType::tryFrom($currentFilters['invoices']['invoiceTypes']),
                balanceType: $balanceType,
            );
            $invoices = InvoiceResource::collection($invoices);
        } else if ($currentTab === 'transactions') {
            $transactions = queries()->transaction()->paginate(
                wallet: $wallet,
                balanceType: $balanceType,
            );
            $transactions = TransactionResource::collection($transactions);
        }

        return Inertia::render('Wallet/Index', compact('walletStats', 'invoices', 'transactions', 'tabs', 'filters', 'currentTab', 'currentFilters'));
    }

    public function updateFiatCurrency(UpdateFiatCurrencyRequest $request)
    {
        $request->user()->update([
            'fiat_currency' => strtolower($request->validated('fiat_currency')),
        ]);

        return redirect()->back();
    }
}
