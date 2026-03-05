<?php

namespace App\Contracts;

use App\Models\Order;
use App\Models\Payout\Payout;

interface CallbackServiceContract
{
    public function sendForOrder(Order $order): void;

    public function sendForPayout(Payout $payout): void;
}
