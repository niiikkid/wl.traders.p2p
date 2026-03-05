<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DisputeStatus;
use App\Exceptions\DisputeException;
use App\Http\Controllers\Controller;
use App\Http\Resources\DisputeResource;
use App\Models\Dispute;
use App\Models\Order;
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

        return Inertia::render('Dispute/Index', compact('disputes', 'filters', 'filtersVariants', 'oldestDisputeCreatedAt'));
    }

    public function store(Order $order)
    {
        try {
            services()->dispute()->create($order->id);

            return redirect()->back()->with('message', 'Спор успешно открыт.');
        } catch (DisputeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
