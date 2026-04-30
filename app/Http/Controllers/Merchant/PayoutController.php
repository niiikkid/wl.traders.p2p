<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\Controller;
use App\Http\Resources\Payout\MerchantPayoutResource;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PayoutController extends Controller
{
    public function index(Request $request): Response
    {
        if (! $request->user()->payouts_enabled) {
            abort(403, 'Выплаты для вашего аккаунта отключены.');
        }

        $filters = $this->getTableFilters();
        $filtersVariants = $this->getFiltersData();

        $payouts = queries()->payout()->paginateForMerchant($request->user(), $filters);
        $payouts = MerchantPayoutResource::collection($payouts);

        return Inertia::render('Payout/Merchant/Index', [
            'payouts' => $payouts,
            'filters' => $filters,
            'filtersVariants' => $filtersVariants,
        ]);
    }
}

