<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\CascadeServiceContract;
use App\Exceptions\CascadeException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CascadeDeal\OpenDisputeRequest;
use App\Http\Resources\TableCascadeDealResource;
use App\Models\CascadeDeal;
use Illuminate\Support\Arr;
use Inertia\Inertia;

class CascadeDealController extends Controller
{
    public function index()
    {
        $filters = [];
        $filtersVariants = [];

        $cascadeDeals = CascadeDeal::query()
            ->with([
                'merchant',
                'merchant.user.wallet',
                'merchantClient',
                'order.dispute',
                'selectedProvider',
                'selectedProvider.user.wallet',
                'selectedTransaction',
                'transactions' => fn ($query) => $query
                    ->with('provider')
                    ->latest('id'),
                'events' => fn ($query) => $query
                    ->with(['provider', 'cascadeTransaction'])
                    ->latest('id')
                    ->limit(20),
                'amountChangeEvents' => fn ($query) => $query
                    ->latest('id')
                    ->limit(20),
                'walletTransactions' => fn ($query) => $query
                    ->latest('id')
                    ->limit(50),
                'providerLogs' => fn ($query) => $query
                    ->with(['provider', 'cascadeTransaction'])
                    ->latest('id')
                    ->limit(20),
                'collateralHolds',
            ])
            ->withCount(['transactions', 'providerLogs'])
            ->latest()
            ->paginate(request()->integer('per_page', 10))
            ->withQueryString();

        $cascadeDeals = TableCascadeDealResource::collection($cascadeDeals);

        return Inertia::render('Admin/CascadeDeals/Index', compact('cascadeDeals', 'filters', 'filtersVariants'));
    }

    public function openDispute(OpenDisputeRequest $request, CascadeDeal $cascadeDeal)
    {
        $data = $request->validated();

        try {
            app(CascadeServiceContract::class)->openDispute($cascadeDeal, $data);

            return redirect()->back()->with('message', 'Спор успешно открыт.');
        } catch (CascadeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function receipt(CascadeDeal $cascadeDeal, int $receipt)
    {
        $hasDisputeFootprint = $cascadeDeal->dispute_status !== null
            || (($cascadeDeal->dispute_history ?? []) !== [])
            || (($cascadeDeal->dispute_receipts ?? []) !== []);
        abort_unless($hasDisputeFootprint, 404);

        $files = collect($cascadeDeal->dispute_receipts ?? [])
            ->flatMap(fn (array $batch): array => Arr::wrap($batch['files'] ?? []))
            ->values();

        $file = $files->get($receipt);
        abort_unless(is_array($file) && ! empty($file['stored_name']), 404);

        $filePath = storage_path('receipts/cascade/'.$file['stored_name']);
        abort_unless(is_file($filePath), 404);

        return response()->file($filePath);
    }
}
