<?php

namespace App\Http\Controllers\Support;

use App\Enums\DisputeStatus;
use App\Exceptions\DisputeException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dispute\CancelRequest;
use App\Http\Resources\DisputeResource;
use App\Models\Dispute;
use App\Models\Order;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class DisputeController extends Controller
{
    public function index()
    {
        $filters = $this->getTableFilters();
        $filtersVariants = $this->getFiltersData();

        $disputes = queries()->dispute()->paginateForAdmin($filters);

        $disputes = DisputeResource::collection($disputes);

        $oldestDisputeCreatedAt = Dispute::query()
            ->where('status', DisputeStatus::PENDING)
            ->oldest('created_at')
            ->first('created_at')
            ?->created_at
            ->toDateTimeString();

        return Inertia::render('Support/Dispute/Index', compact('disputes', 'filters', 'filtersVariants', 'oldestDisputeCreatedAt'));
    }

    public function store(Order $order)
    {
        Gate::authorize('access-to-order', $order);

        try {
            services()->dispute()->create($order->id);

            return redirect()->back()->with('message', 'Спор успешно открыт.');
        } catch (DisputeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function accept(Dispute $dispute)
    {
        Gate::authorize('access-to-dispute', $dispute);

        services()->dispute()->accept($dispute->id);
    }

    public function cancel(CancelRequest $request, Dispute $dispute)
    {
        Gate::authorize('access-to-dispute', $dispute);

        services()->dispute()->cancel($dispute->id, $request->reason);
    }

    public function rollback(Dispute $dispute)
    {
        Gate::authorize('access-to-dispute', $dispute);

        services()->dispute()->rollback($dispute->id);
    }
} 