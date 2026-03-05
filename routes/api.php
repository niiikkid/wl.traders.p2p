<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::group(['middleware' => ['api-access-token']], function () {
    //common
    Route::get('payment-gateways', [\App\Http\Controllers\API\PaymentGatewayController::class, 'index']);
    Route::get('currencies', [\App\Http\Controllers\API\CurrencyController::class, 'index']);
    Route::group(['prefix' => 'merchant'], function () {
        Route::get('order/{order:uuid}', [\App\Http\Controllers\API\Merchant\OrderController::class, 'show']);
        Route::get('order/{merchant_id}/{external_id}', [\App\Http\Controllers\API\Merchant\OrderController::class, 'showByExternal']);
        Route::post('order', [\App\Http\Controllers\API\Merchant\OrderController::class, 'store'])->name('api.order');
    });

    Route::group(['prefix' => 'h2h'], function () {
        Route::get('order/{order:uuid}', [\App\Http\Controllers\API\H2H\OrderController::class, 'show']);
        Route::post('order', [\App\Http\Controllers\API\H2H\OrderController::class, 'store']);
        Route::patch('order/{order:uuid}/cancel', [\App\Http\Controllers\API\H2H\OrderController::class, 'cancel']);
        Route::patch('order/{order:uuid}/finish', [\App\Http\Controllers\API\H2H\OrderController::class, 'finish']);

        //TODO
        //Route::patch('order/{order:uuid}/confirm-paid', [\App\Http\Controllers\API\H2H\OrderController::class, 'cancel']);

        Route::post('order/{order:uuid}/dispute', [\App\Http\Controllers\API\H2H\DisputeController::class, 'store'])->name('api.dispute');
        Route::get('order/{order:uuid}/dispute', [\App\Http\Controllers\API\H2H\DisputeController::class, 'show']);
        Route::get('order/{merchant_id}/{external_id}', [\App\Http\Controllers\API\H2H\OrderController::class, 'showByExternal']);
    });

    Route::group(['prefix' => 'wallet'], function () {
        Route::get('balance', [\App\Http\Controllers\API\Merchant\WalletController::class, 'balance']);
        Route::post('withdraw', [\App\Http\Controllers\API\Merchant\WalletController::class, 'withdraw']);
    });

    Route::group(['prefix' => 'payouts'], function () {
        Route::post('/', [\App\Http\Controllers\API\Payout\PayoutController::class, 'store'])->name('api.payouts.store');
        Route::get('{payout:uuid}', [\App\Http\Controllers\API\Payout\PayoutController::class, 'show'])->name('api.payouts.show');
        Route::patch('{payout:uuid}/cancel', [\App\Http\Controllers\API\Payout\PayoutController::class, 'cancel'])->name('api.payouts.cancel');
        Route::patch('{payout:uuid}/confirm-paid', [\App\Http\Controllers\API\Payout\PayoutController::class, 'confirmPaid'])->name('api.payouts.confirm-paid');
        Route::get('{payout:uuid}/receipt', [\App\Http\Controllers\API\Payout\PayoutReceiptController::class, 'show'])->name('api.payouts.receipt');
    });

    Route::group(['prefix' => 'statements'], function () {
        Route::get('orders', [\App\Http\Controllers\API\Statement\StatementController::class, 'orders'])
            ->name('api.statements.orders');
        Route::get('payouts', [\App\Http\Controllers\API\Statement\StatementController::class, 'payouts'])
            ->name('api.statements.payouts');
    });
});

Route::group(['prefix' => 'deposit', 'middleware' => ['api-deposits-access-token']], function () {
    Route::post('webhook', [\App\Http\Controllers\API\Deposit\DepositController::class, 'webhook']);
});

Route::group(['prefix' => 'withdraw', 'middleware' => ['api-withdrawals-access-token']], function () {
    Route::post('webhook', [\App\Http\Controllers\API\Withdraw\WithdrawController::class, 'webhook']);
});

Route::group(['prefix' => 'app', 'middleware' => ['device-access-token']], function () {
    Route::post('sms', [\App\Http\Controllers\API\APP\SmsController::class, 'store'])->middleware('idempotency_for_app');
    Route::get('state', [\App\Http\Controllers\API\APP\StateController::class, 'index']);
    Route::get('device/ping', [\App\Http\Controllers\API\APP\DeviceController::class, 'ping']);
    Route::post('device/connect', [\App\Http\Controllers\API\APP\DeviceController::class, 'connect']);
});

if (app()->environment(['local', 'dev', 'development'])) {
Route::post('/test/h2h-callback', function (\Illuminate\Http\Request $request) {
    return response()->json([
        'success' => true,
        'message' => 'Callback delivered',
        'received' => $request->all(),
    ]);
});

Route::post('/sandbox/payout-callback', function (\Illuminate\Http\Request $request) {
    return response()->json([
        'success' => true,
        'message' => 'Sandbox callback delivered',
        'received' => $request->all(),
        'timestamp' => now()->toIso8601String(),
    ]);
});
}

// Коллбэк от внешнего сервиса инвойсов (публичный, без токенов)
Route::post('/v1/callbacks/invoice', [\App\Http\Controllers\API\Deposit\DepositController::class, 'externalWebhook'])
    ->name('api.external.invoice.callback');
