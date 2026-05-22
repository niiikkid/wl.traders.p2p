<?php

namespace App\Http\Controllers;

use App\Http\Requests\Dispute\CancelRequest;
use App\Http\Resources\DisputeResource;
use App\Models\Dispute;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class DisputeController extends Controller
{
    public function index()
    {
        $filters = $this->getTableFilters();
        $filtersVariants = $this->getFiltersData();

        $disputes = queries()->dispute()->paginateForUser(auth()->user(), $filters);

        $disputes = DisputeResource::collection($disputes);

        return Inertia::render('Dispute/Index', compact('disputes', 'filters', 'filtersVariants'));
    }

    public function accept(Dispute $dispute)
    {
        Gate::authorize('access-to-dispute', $dispute);

        services()->dispute()->accept($dispute->id);
    }

    public function cancel(CancelRequest $request, Dispute $dispute)
    {
        Gate::authorize('access-to-dispute', $dispute);

        services()->dispute()->cancel(
            $dispute->id,
            $request->validatedReasonCode(),
            $request->validated('reason'),
            $request->file('bank_statement'),
        );
    }

    public function rollback(Dispute $dispute)
    {
        Gate::authorize('access-to-dispute', $dispute);

        services()->dispute()->rollback($dispute->id);
    }

    public function receipt(Dispute $dispute)
    {
        Gate::authorize('access-to-dispute-receipt', $dispute);

        $file_path = storage_path('receipts/'.$dispute->receipt);

        return response()->file($file_path);
    }

    public function bankStatement(Dispute $dispute)
    {
        Gate::authorize('access-to-dispute-bank-statement', $dispute);

        if (! $dispute->bank_statement) {
            abort(404);
        }

        $file_path = storage_path('dispute-bank-statements/'.$dispute->bank_statement);

        if (! is_file($file_path)) {
            abort(404);
        }

        return response()->file($file_path);
    }
}
