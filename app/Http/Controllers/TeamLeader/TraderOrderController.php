<?php

namespace App\Http\Controllers\TeamLeader;

use App\Http\Controllers\Controller;
use App\Http\Resources\TableOrderResource;
use App\Http\Resources\TeamLeader\TeamLeaderTraderResource;
use App\Models\User;
use Inertia\Inertia;

class TraderOrderController extends Controller
{
    public function index(User $trader)
    {
        $this->authorizeExtendedAccess();
        $this->authorizeTraderAccess($trader);

        $filters = $this->getTableFilters();
        $filtersVariants = $this->getFiltersData();

        $orders = queries()
            ->order()
            ->paginateForTeamLeader(auth()->user(), $trader, $filters);

        $orders = TableOrderResource::collection($orders);
        $trader = TeamLeaderTraderResource::make($trader)->resolve();

        return Inertia::render('Leader/Trader/Orders', compact('trader', 'orders', 'filters', 'filtersVariants'));
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

