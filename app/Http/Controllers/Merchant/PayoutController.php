<?php

namespace App\Http\Controllers\Merchant;

use App\Exceptions\PayoutException;
use App\Http\Controllers\Controller;
use App\Http\Resources\Payout\MerchantPayoutResource;
use App\Models\Payout\Payout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
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

    public function confirmPaid(Request $request, Payout $payout): RedirectResponse
    {
        if (! $request->user()->payouts_enabled) {
            abort(403, 'Выплаты для вашего аккаунта отключены.');
        }

        Gate::authorize('access-to-merchant', $payout->merchant);

        try {
            services()->payout()->confirmPaid($payout);
        } catch (PayoutException $exception) {
            return redirect()->back()->with('error', $exception->getMessage());
        }

        return redirect()->back()->with('message', 'Выплата подтверждена, холд снят.');
    }
}
