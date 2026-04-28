<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\Controller;
use App\Jobs\SendOrderCallbackJob;
use App\Models\Order;
use Illuminate\Support\Facades\Gate;

class ResendCallbackController extends Controller
{
    public function resend(Order $order)
    {
        Gate::authorize('access-to-order', $order);

        SendOrderCallbackJob::dispatch($order);

        return redirect()->back()->with('message', 'Callback о текущем статусе повторно отправлен.');
    }
}
