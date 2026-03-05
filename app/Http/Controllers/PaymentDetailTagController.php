<?php

namespace App\Http\Controllers;

use App\Http\Requests\PaymentDetailTag\StoreRequest;
use App\Http\Requests\PaymentDetailTag\UpdateRequest;
use App\Http\Resources\PaymentDetailTagResource;
use App\Models\PaymentDetailTag;

class PaymentDetailTagController extends Controller
{
    public function store(StoreRequest $request)
    {
        $this->ensureTrader();

        $tag = PaymentDetailTag::create([
            'user_id' => $request->user()->id,
            'name' => $request->name,
            'color' => $request->color,
        ]);

        return response()->json([
            'success' => true,
            'data' => PaymentDetailTagResource::make($tag)->resolve(),
        ]);
    }

    public function update(UpdateRequest $request, PaymentDetailTag $paymentDetailTag)
    {
        $this->ensureTrader();
        $this->ensureOwner($paymentDetailTag);

        $paymentDetailTag->update([
            'name' => $request->name,
            'color' => $request->color,
        ]);

        return response()->json([
            'success' => true,
            'data' => PaymentDetailTagResource::make($paymentDetailTag)->resolve(),
        ]);
    }

    public function destroy(PaymentDetailTag $paymentDetailTag)
    {
        $this->ensureTrader();
        $this->ensureOwner($paymentDetailTag);

        $paymentDetailTag->delete();

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

    private function ensureOwner(PaymentDetailTag $paymentDetailTag): void
    {
        if ($paymentDetailTag->user_id !== auth()->id()) {
            abort(403);
        }
    }
}
