<?php

namespace App\Http\Controllers\Support;

use App\Enums\InvoiceType;
use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Services\Money\Currency;
use App\Services\Money\Money;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DepositController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless((bool) $request->user()?->support_can_view_deposits, 403);

        $filters = $this->getTableFilters();
        $filtersVariants = $this->getFiltersData();

        $invoices = Invoice::query()
            ->with(['wallet.user', 'wallet.merchant'])
            ->where('type', InvoiceType::DEPOSIT)
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
            ->orderByDesc('id')
            ->paginate(request()->per_page ?? 10);

        $invoices = InvoiceResource::collection($invoices);

        return Inertia::render('Support/Deposit/Index', compact('invoices', 'filters', 'filtersVariants'));
    }
}
