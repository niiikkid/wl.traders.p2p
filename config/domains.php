<?php

$appUrl = rtrim((string) env('APP_URL', 'http://localhost'), '/');
$paymentUrl = rtrim((string) (env('PAYMENT_FORM_URL') ?: $appUrl), '/');

$appHost = parse_url($appUrl, PHP_URL_HOST);
$paymentHost = parse_url($paymentUrl, PHP_URL_HOST);
$appHost = is_string($appHost) ? $appHost : null;
$paymentHost = is_string($paymentHost) ? $paymentHost : null;

return [
    'app_url' => $appUrl,
    'payment_url' => $paymentUrl,
    'app_host' => $appHost,
    'payment_host' => $paymentHost,
    'legacy_redirect_status' => (int) env('PAYMENT_LEGACY_REDIRECT_STATUS', 301),
];
