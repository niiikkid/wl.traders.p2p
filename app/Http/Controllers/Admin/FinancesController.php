<?php

namespace App\Http\Controllers\Admin;

use App\Enums\InvoiceType;
use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Services\Money\Currency;
use App\Services\Money\Money;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FinancesController extends Controller
{
    public function index(Request $request): Response
    {
        $tab = $request->query('tab', 'deposits');
        if (! in_array($tab, ['deposits', 'withdrawals'], true)) {
            $tab = 'deposits';
        }

        $invoiceType = $tab === 'withdrawals'
            ? InvoiceType::WITHDRAWAL
            : InvoiceType::DEPOSIT;

        $filters = $this->getTableFilters();
        $filtersVariants = $this->getFiltersData();

        $invoices = Invoice::query()
            ->with('wallet.user')
            ->where('type', $invoiceType)
            ->when(! empty($filters->invoiceStatuses), function ($query) use ($filters) {
                $query->whereIn('status', $filters->invoiceStatuses);
            })
            ->when($filters->id, function ($query) use ($filters) {
                $query->where('id', $filters->id);
            })
            ->when($filters->amount, function ($query) use ($filters) {
                $amount = Money::fromPrecision($filters->amount, Currency::USDT())->toUnits();
                $query->where('amount', $amount);
            })
            ->when($filters->user, function ($query) use ($filters) {
                $query->where(function ($query) use ($filters) {
                    $query->whereRelation('wallet.user', 'email', 'like', '%'.$filters->user.'%');
                    $query->orWhereRelation('wallet.user', 'name', 'like', '%'.$filters->user.'%');
                });
            })
            ->when($tab === 'withdrawals' && $filters->address, function ($query) use ($filters) {
                $query->where('address', 'LIKE', '%'.$filters->address.'%');
            })
            ->orderByDesc('id')
            ->paginate($request->integer('per_page') ?: 10)
            ->withQueryString();

        return Inertia::render('Admin/Finances/Index', [
            'invoices' => InvoiceResource::collection($invoices),
            'filters' => $filters,
            'filtersVariants' => $filtersVariants,
            'tab' => $tab,
        ]);
    }
}
