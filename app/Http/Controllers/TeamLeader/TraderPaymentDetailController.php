<?php

namespace App\Http\Controllers\TeamLeader;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentDetailResource;
use App\Http\Resources\TeamLeader\TeamLeaderTraderResource;
use App\Models\User;
use Inertia\Inertia;

class TraderPaymentDetailController extends Controller
{
    public function index(User $trader)
    {
        $this->authorizeExtendedAccess();
        $this->authorizeTraderAccess($trader);

        $filters = $this->getTableFilters();
        $filtersVariants = $this->getFiltersData();
        $fromArchive = request()->tab === 'archived';

        $paymentDetails = queries()
            ->paymentDetail()
            ->paginateForTeamLeader(auth()->user(), $trader, $filters, $fromArchive);

        $paymentDetails = PaymentDetailResource::collection($paymentDetails);
        $trader = TeamLeaderTraderResource::make($trader)->resolve();

        return Inertia::render('Leader/Trader/PaymentDetails', compact('trader', 'paymentDetails', 'filters', 'filtersVariants'));
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

