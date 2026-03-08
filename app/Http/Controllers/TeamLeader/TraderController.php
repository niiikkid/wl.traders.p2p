<?php

namespace App\Http\Controllers\TeamLeader;

use App\Http\Controllers\Controller;
use App\Http\Resources\TeamLeader\TeamLeaderTraderResource;
use App\Models\User;
use Inertia\Inertia;

class TraderController extends Controller
{
    public function index()
    {
        $this->authorizeExtendedAccess();

        $filters = $this->getTableFilters();

        $traders = User::query()
            ->role('Trader')
            ->where('team_leader_id', auth()->id())
            ->when($filters->user, function ($query) use ($filters) {
                $query->where(function ($builder) use ($filters) {
                    $builder->where('email', 'like', '%' . $filters->user . '%')
                        ->orWhere('name', 'like', '%' . $filters->user . '%');
                });
            })
            ->when($filters->online, function ($query) {
                $query->where('is_online', true);
            })
            ->when($filters->traffic_disabled, function ($query) {
                $query->where('stop_traffic', true);
            })
            ->withCount([
                'paymentDetails as payment_details_count' => function ($query) {
                    $query->whereNull('archived_at');
                },
            ])
            ->orderByDesc('id')
            ->paginate(request()->per_page ?? 10);

        $traders = TeamLeaderTraderResource::collection($traders);
        $filtersVariants = $this->getFiltersData();

        return Inertia::render('Leader/Trader/Index', compact('traders', 'filters', 'filtersVariants'));
    }

    public function show(User $trader)
    {
        $this->authorizeExtendedAccess();
        $this->authorizeTraderAccess($trader);

        return redirect()->route('leader.traders.payment-details.index', ['trader' => $trader->id]);
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

