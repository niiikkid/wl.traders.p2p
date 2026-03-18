<?php

$appUrl = rtrim((string) env('APP_URL', 'http://localhost'), '/');
$paymentUrl = rtrim((string) (env('PAYMENT_FORM_URL') ?: $appUrl), '/');

return [
    'app_url' => $appUrl,
    'payment_url' => $paymentUrl,
    'app_host' => parse_url($appUrl, PHP_URL_HOST),
    'payment_host' => parse_url($paymentUrl, PHP_URL_HOST),
    'legacy_redirect_status' => (int) env('PAYMENT_LEGACY_REDIRECT_STATUS', 301),
];
