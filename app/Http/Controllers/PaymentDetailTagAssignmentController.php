<?php

namespace App\Http\Controllers;

use App\Http\Requests\PaymentDetailTag\SyncRequest;
use App\Models\PaymentDetail;
use Illuminate\Support\Facades\Gate;

class PaymentDetailTagAssignmentController extends Controller
{
    public function update(SyncRequest $request, PaymentDetail $paymentDetail)
    {
        $this->ensureTrader();
        Gate::authorize('access-to-payment-detail', $paymentDetail);

        $tagIds = $request->input('tags', []);

        $paymentDetail->tags()->sync($tagIds);

        return response()->json([
            'success' => true,
        ]);
    }

    private function ensureTrader(): void
    {
        if (! isRouteFor('Trader')) {
            abort(403);
        }
    }
}
