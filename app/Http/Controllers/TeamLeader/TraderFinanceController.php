<?php

namespace App\Http\Controllers\TeamLeader;

use App\Enums\BalanceType;
use App\Enums\InvoiceType;
use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\TeamLeader\TeamLeaderTraderResource;
use App\Http\Resources\TransactionResource;
use App\Models\User;
use Inertia\Inertia;

class TraderFinanceController extends Controller
{
    public function index(User $trader)
    {
        $this->authorizeExtendedAccess();
        $this->authorizeTraderAccess($trader);

        $wallet = $trader->wallet;

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

        $walletStats = services()->wallet()->getWalletStats($wallet)->toArray();

        $invoices = null;
        $transactions = null;

        if ($currentTab === 'invoices') {
            $invoices = queries()->invoice()->paginate(
                wallet: $wallet,
                invoiceType: InvoiceType::tryFrom($currentFilters['invoices']['invoiceTypes']),
                balanceType: BalanceType::TRUST,
            );
            $invoices = InvoiceResource::collection($invoices);
        } else {
            $transactions = queries()->transaction()->paginate(
                wallet: $wallet,
                balanceType: BalanceType::TRUST,
            );
            $transactions = TransactionResource::collection($transactions);
        }

        $trader = TeamLeaderTraderResource::make($trader)->resolve();

        return Inertia::render('Leader/Trader/Finances', compact(
            'trader',
            'walletStats',
            'invoices',
            'transactions',
            'tabs',
            'filters',
            'currentTab',
            'currentFilters'
        ));
    }

    private function authorizeTraderAccess(User $trader): void
    {
        abort_if(! $trader->hasRole('Trader'), 404);
        abort_unless((int) $trader->team_leader_id === (int) auth()->id(), 403);
    }

    private function authorizeExtendedAccess(): void
    {
        abort_unless((bool) auth()->user()?->team_leader_extended_access_enabled, 403);
    }
}

