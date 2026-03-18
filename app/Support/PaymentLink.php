<?php

namespace App\Support;

class PaymentLink
{
    public static function order(string $orderUUID): string
    {
        $baseUrl = rtrim((string) config('domains.payment_url', config('app.url')), '/');
        $path = route('payment.show', $orderUUID, false);

        return $baseUrl.$path;
    }
}
