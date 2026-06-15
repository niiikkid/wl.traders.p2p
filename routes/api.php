<?php

use App\Http\Controllers\API\APP\DeviceController;
use App\Http\Controllers\API\APP\SmsController;
use App\Http\Controllers\API\APP\StateController;
use App\Http\Controllers\API\CurrencyController;
use App\Http\Controllers\API\Deposit\DepositController;
use App\Http\Controllers\API\H2H\DisputeController;
use App\Http\Controllers\API\H2H\OrderController;
use App\Http\Controllers\API\Integration\InfrastructureController as IntegrationInfrastructureController;
use App\Http\Controllers\API\Merchant\WalletController;
use App\Http\Controllers\API\PaymentGatewayController;
use App\Http\Controllers\API\Payout\PayoutController;
use App\Http\Controllers\API\Payout\PayoutReceiptController;
use App\Http\Controllers\API\Statement\StatementController;
use App\Http\Controllers\API\Withdraw\WithdrawController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::group(['middleware' => ['api-access-token']], function () {
    // common
    Route::get('payment-gateways', [PaymentGatewayController::class, 'index']);
    Route::get('currencies', [CurrencyController::class, 'index']);
    Route::group(['prefix' => 'h2h'], function () {
        Route::get('order/{order:uuid}', [OrderController::class, 'show']);
        Route::post('order', [OrderController::class, 'store']);
        Route::patch('order/{order:uuid}/cancel', [OrderController::class, 'cancel']);
        Route::patch('order/{order:uuid}/finish', [OrderController::class, 'finish']);
        Route::post('order/{order:uuid}/confirmation-code', [OrderController::class, 'storeConfirmationCode']);

        // TODO
        // Route::patch('order/{order:uuid}/confirm-paid', [\App\Http\Controllers\API\H2H\OrderController::class, 'cancel']);

        Route::post('order/{order:uuid}/dispute', [DisputeController::class, 'store'])->name('api.dispute');
        Route::get('order/{order:uuid}/dispute', [DisputeController::class, 'show']);
        Route::get('order/{merchant_id}/{external_id}', [OrderController::class, 'showByExternal']);
    });

    Route::group(['prefix' => 'wallet'], function () {
        Route::get('balance', [WalletController::class, 'balance']);
    });

    Route::group(['prefix' => 'payouts'], function () {
        Route::post('/', [PayoutController::class, 'store'])->name('api.payouts.store');
        Route::get('{payout:uuid}', [PayoutController::class, 'show'])->name('api.payouts.show');
        Route::patch('{payout:uuid}/cancel', [PayoutController::class, 'cancel'])->name('api.payouts.cancel');
        Route::patch('{payout:uuid}/confirm-paid', [PayoutController::class, 'confirmPaid'])->name('api.payouts.confirm-paid');
        Route::get('{payout:uuid}/receipt', [PayoutReceiptController::class, 'show'])->name('api.payouts.receipt');
        Route::get('{payout:uuid}/receipts', [PayoutReceiptController::class, 'index'])->name('api.payouts.receipts');
    });

    Route::group(['prefix' => 'statements'], function () {
        Route::get('orders', [StatementController::class, 'orders'])
            ->name('api.statements.orders');
        Route::get('payouts', [StatementController::class, 'payouts'])
            ->name('api.statements.payouts');
    });

});

Route::group(['prefix' => 'deposit', 'middleware' => ['api-deposits-access-token']], function () {
    Route::post('webhook', [DepositController::class, 'webhook']);
});

Route::group(['prefix' => 'withdraw', 'middleware' => ['api-withdrawals-access-token']], function () {
    Route::post('webhook', [WithdrawController::class, 'webhook']);
});

Route::group(['prefix' => 'app', 'middleware' => ['device-access-token']], function () {
    Route::post('sms', [SmsController::class, 'store']);
    Route::get('state', [StateController::class, 'index']);
    Route::get('device/ping', [DeviceController::class, 'ping']);
    Route::post('device/connect', [DeviceController::class, 'connect']);
});

Route::group([
    'prefix' => 'integration/v1',
    'middleware' => ['integration-infrastructure-api-access-token'],
], function () {
    Route::get('users', [IntegrationInfrastructureController::class, 'users']);
    Route::get('users/{user}', [IntegrationInfrastructureController::class, 'user']);
    Route::get('users/{user}/offers', [IntegrationInfrastructureController::class, 'userOffers']);

    Route::get('payment-details', [IntegrationInfrastructureController::class, 'paymentDetails']);
    Route::get('payment-details/{paymentDetail}', [IntegrationInfrastructureController::class, 'paymentDetail']);

    Route::get('orders', [IntegrationInfrastructureController::class, 'orders']);
    Route::get('orders/{order:uuid}', [IntegrationInfrastructureController::class, 'order']);

    Route::get('disputes', [IntegrationInfrastructureController::class, 'disputes']);
    Route::get('disputes/{dispute}', [IntegrationInfrastructureController::class, 'dispute']);

    Route::get('invoices', [IntegrationInfrastructureController::class, 'invoices']);
    Route::get('invoices/{invoice}', [IntegrationInfrastructureController::class, 'invoice']);
    Route::get('deposits', [IntegrationInfrastructureController::class, 'invoices'])->defaults('type', 'deposit');
    Route::get('withdrawals', [IntegrationInfrastructureController::class, 'invoices'])->defaults('type', 'withdrawal');

    Route::get('payouts', [IntegrationInfrastructureController::class, 'payouts']);
    Route::get('payouts/{payout:uuid}', [IntegrationInfrastructureController::class, 'payout']);

    Route::get('wallets/{wallet}', [IntegrationInfrastructureController::class, 'wallet']);
    Route::get('wallets/{wallet}/transactions', [IntegrationInfrastructureController::class, 'walletTransactions']);
    Route::get('wallets/{wallet}/transactions/{transaction}', [IntegrationInfrastructureController::class, 'walletTransaction']);
});

if (app()->environment(['local', 'dev', 'development'])) {
    Route::post('/test/h2h-callback', function (Request $request) {
        return response()->json([
            'success' => true,
            'message' => 'Callback delivered',
            'received' => $request->all(),
        ]);
    });
}

// Коллбэк от внешнего сервиса инвойсов (публичный, без токенов)
Route::post('/v1/callbacks/invoice', [DepositController::class, 'externalWebhook'])
    ->name('api.external.invoice.callback');
