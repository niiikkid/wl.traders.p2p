<?php

namespace App\Http\Controllers\Support;

use App\Enums\DisputeStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\DisputeResource;
use App\Models\Dispute;
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
} 