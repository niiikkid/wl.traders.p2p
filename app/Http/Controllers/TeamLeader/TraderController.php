<?php

namespace App\Http\Controllers\TeamLeader;

use App\Http\Controllers\Controller;
use App\Http\Requests\Leader\Trader\UpdateCommissionRequest;
use App\Http\Resources\TeamLeader\TeamLeaderTraderResource;
use App\Models\User;
use App\Support\TeamLeaderTraderCommissionResolver;
use Illuminate\Http\Request;
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
            ->whereNull('archived_at')
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
        $teamLeader = auth()->user();
        $commissionSettings = [
            'flexible_enabled' => TeamLeaderTraderCommissionResolver::isFlexibleEnabled($teamLeader),
            'min' => $teamLeader->team_leader_flexible_trader_commission_min !== null
                ? (float) $teamLeader->team_leader_flexible_trader_commission_min
                : null,
            'max' => $teamLeader->team_leader_flexible_trader_commission_max !== null
                ? (float) $teamLeader->team_leader_flexible_trader_commission_max
                : null,
        ];

        return Inertia::render('Leader/Trader/Index', compact('traders', 'filters', 'filtersVariants', 'commissionSettings'));
    }

    public function show(User $trader)
    {
        $this->authorizeExtendedAccess();
        $this->authorizeTraderAccess($trader);

        return redirect()->route('leader.traders.payment-details.index', ['trader' => $trader->id]);
    }

    public function toggleOnline(Request $request, User $trader): void
    {
        $this->authorizeExtendedAccess();
        $this->authorizeTraderAccess($trader);

        if ((int) $trader->is_online !== (int) $request->is_online) {
            if ($trader->stop_traffic && (int) $request->is_online) {
                return;
            }

            $trader->update(['is_online' => ! $trader->is_online]);
        }
    }

    public function updateCommission(UpdateCommissionRequest $request, User $trader)
    {
        $this->authorizeExtendedAccess();
        $this->authorizeTraderAccess($trader);

        $teamLeader = auth()->user();
        abort_unless(TeamLeaderTraderCommissionResolver::isFlexibleEnabled($teamLeader), 403);

        $trader->update([
            'team_leader_individual_commission_percentage' => (float) $request->validated('commission'),
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'team_leader_individual_commission_percentage' => (float) $trader->team_leader_individual_commission_percentage,
                'team_leader_effective_commission_percentage' => TeamLeaderTraderCommissionResolver::resolveEffectiveRate($teamLeader, $trader),
            ],
        ]);
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

