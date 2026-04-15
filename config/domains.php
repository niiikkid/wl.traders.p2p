<?php

$appUrl = rtrim((string) env('APP_URL', 'http://localhost'), '/');
$paymentUrl = rtrim((string) (env('PAYMENT_FORM_URL') ?: $appUrl), '/');
$landingUrl = rtrim((string) (env('LANDING_URL') ?: ''), '/');

$appHost = parse_url($appUrl, PHP_URL_HOST);
$paymentHost = parse_url($paymentUrl, PHP_URL_HOST);
$appHost = is_string($appHost) ? $appHost : null;
$paymentHost = is_string($paymentHost) ? $paymentHost : null;

$landingHost = $landingUrl !== '' ? parse_url($landingUrl, PHP_URL_HOST) : null;
$landingHost = is_string($landingHost) ? $landingHost : null;

/** Хост публичного лендинга: из LANDING_URL, иначе платёжный домен, если он не совпадает с APP_URL. */
$marketingHost = $landingHost;
if ($marketingHost === null
    && $paymentHost !== null
    && $appHost !== null
    && strtolower($paymentHost) !== strtolower($appHost)
) {
    $marketingHost = $paymentHost;
}

return [
    'app_url' => $appUrl,
    'payment_url' => $paymentUrl,
    'app_host' => $appHost,
    'payment_host' => $paymentHost,
    'marketing_host' => $marketingHost,
    /**
     * Раздельно: кабинет только на app_host, лендинг на marketing_host.
     * На APP_URL (`/`) всегда вход или редирект в бэк офис, без лендинга.
     */
    'split_marketing' => $appHost !== null
        && $marketingHost !== null
        && strtolower($appHost) !== strtolower($marketingHost),
    'legacy_redirect_status' => (int) env('PAYMENT_LEGACY_REDIRECT_STATUS', 301),
];
