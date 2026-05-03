<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\Controller;
use App\Jobs\SendPayoutCallbackJob;
use App\Models\Payout\Payout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PayoutCallbackController extends Controller
{
    public function resend(Request $request, Payout $payout)
    {
        Gate::authorize('access-to-merchant', $payout->merchant);

        $callbackRevision = null;

        if ($payout->api_version === 2) {
            $callbackRevision = $payout->callback_payload_revision + 1;
            $payout->forceFill(['callback_payload_revision' => $callbackRevision])->save();
        }

        SendPayoutCallbackJob::dispatch($payout, $callbackRevision);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Callback о текущем статусе выплаты повторно отправлен.',
            ]);
        }

        return back()->with('message', 'Callback о текущем статусе выплаты повторно отправлен.');
    }
}



