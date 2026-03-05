<?php

use App\Http\Controllers\NotificationController;
use App\Http\Controllers\NotificationRuleController;
use App\Http\Controllers\PayoutReceiptController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TelegramSettingsController;
use App\Http\Controllers\TelegramWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/payment/{order:uuid}', [\App\Http\Controllers\PaymentLinkController::class, 'show'])->name('payment.show');
Route::post('/payment/{order:uuid}/dispute', [\App\Http\Controllers\PaymentLinkController::class, 'storeDispute'])->name('payment.dispute.store');
Route::post('/payment/{order:uuid}/payment-detail/{paymentGateway}', [\App\Http\Controllers\PaymentLinkController::class, 'storePaymentDetail'])->name('payment.payment-detail.store');

Route::post('/telegram/webhook', TelegramWebhookController::class)
    ->middleware('telegram.secret')
    ->name('telegram.webhook');

// Выход из режима Impersonate
Route::post('/impersonate/leave', function () {
    if (auth()->user()->isImpersonated()) {
        auth()->user()->leaveImpersonation();
        return redirect()->route('admin.users.index');
    }
    return redirect()->back()->with('error', 'Вы не в режиме Impersonate');
})->middleware('auth', 'banned')->name('impersonate.leave');

Route::group(['middleware' => ['2fa']], function () {
    Route::group(['middleware' => ['auth', 'banned']], function () {
        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::patch('/profile/avatar', [ProfileController::class, 'updateAvatar'])->name('profile.update.avatar');
        Route::patch('/profile/auth2fa', [ProfileController::class, 'updateAuth2fa'])->name('profile.update.auth2fa');
        Route::patch('/wallet/fiat-currency', [\App\Http\Controllers\WalletController::class, 'updateFiatCurrency'])->name('wallet.fiat-currency.update');
    });

    Route::group(['middleware' => ['auth', 'banned']], function () {
        Route::get('/', function () {

            if (auth()->user()->hasRole('Merchant')) {
                return redirect()->route('merchant.main.index');
            }

            if (auth()->user()->hasRole('Trader')) {
                return redirect()->route('trader.main.index');
            }

            if (auth()->user()->hasRole('Support')) {
                return redirect()->route('support.users.index');
            }

            if (auth()->user()->hasRole('Team Leader')) {
                return redirect()->route('leader.main.index');
            }

            if (auth()->user()->hasRole('Merchant Support')) {
                return redirect()->route('merchant-support.payments.index');
            }

            return redirect()->route('admin.main.index');
            //return Inertia::render('Dashboard');
        })->name('dashboard');

        Route::post('/invoice', [\App\Http\Controllers\InvoiceController::class, 'store'])->name('invoice.store');
        Route::patch('/user/online', [\App\Http\Controllers\UserOnlineController::class, 'toggle'])->name('user.online.toggle');
        Route::get('/payouts/{payout:uuid}/receipt', [PayoutReceiptController::class, 'show'])->name('payouts.receipts.show');
    });

    Route::group(['middleware' => ['auth', 'banned', 'role:Trader|Merchant|Super Admin']], function () {
        Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllRead'])->name('notifications.mark-all-read');
        Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
        Route::patch('/notifications/{notification}/unread', [NotificationController::class, 'markUnread'])->name('notifications.unread');
        Route::post('/notifications/rules', [NotificationRuleController::class, 'store'])->name('notifications.rules.store');
        Route::patch('/notifications/rules/{notificationRule}', [NotificationRuleController::class, 'update'])->name('notifications.rules.update');
        Route::delete('/notifications/rules/{notificationRule}', [NotificationRuleController::class, 'destroy'])->name('notifications.rules.destroy');
        Route::post('/notifications/telegram/link', [TelegramSettingsController::class, 'refreshLink'])->name('notifications.telegram.link');
        Route::post('/notifications/telegram/unlink', [TelegramSettingsController::class, 'unlink'])->name('notifications.telegram.unlink');
    });

    Route::group(['prefix' => 'leader', 'as'=>'leader.',  'middleware' => ['auth', 'banned', 'role:Team Leader|Super Admin']], function () {
        Route::get('/main', [\App\Http\Controllers\MainPageController::class, 'leader'])->name('main.index');
        Route::get('/finances', [\App\Http\Controllers\WalletController::class, 'index'])->name('finances.index');
        Route::get('/referrals', [\App\Http\Controllers\TeamLeader\ReferralController::class, 'index'])->name('referrals.index');
    });

    Route::group(['middleware' => ['auth', 'banned', 'role:Trader|Support|Super Admin']], function () {
        Route::resource('/orders', \App\Http\Controllers\OrderController::class)->only(['show']);
        Route::get('/disputes/{dispute}/receipt', [\App\Http\Controllers\DisputeController::class, 'receipt'])->name('disputes.receipt');
    });

    Route::group(['middleware' => ['auth', 'banned', 'role:Trader|Super Admin']], function () {
        Route::get('/trader/main', [\App\Http\Controllers\MainPageController::class, 'trader'])->name('trader.main.index');
        Route::post('/trader/temp-vip/activate', [\App\Http\Controllers\Trader\TempVipController::class, 'activate'])->name('trader.temp-vip.activate');

        Route::get('/notifications', [\App\Http\Controllers\Trader\NotificationController::class, 'index'])->name('notifications.index');

        // payouts
        Route::get('/trader/payouts', [\App\Http\Controllers\Trader\PayoutController::class, 'index'])->name('trader.payouts.index');
        Route::post('/trader/payouts/{payout:uuid}/take', [\App\Http\Controllers\Trader\PayoutController::class, 'take'])->name('trader.payouts.take');
        Route::post('/trader/payouts/{payout:uuid}/mark-sent', [\App\Http\Controllers\Trader\PayoutController::class, 'markSent'])->name('trader.payouts.mark-sent');

        // Маршруты для управления устройствами
        Route::get('/trader/devices', [\App\Http\Controllers\UserDeviceController::class, 'index'])->name('trader.devices.index');
        Route::post('/trader/devices', [\App\Http\Controllers\UserDeviceController::class, 'store'])->name('trader.devices.store');
        Route::get('/trader/devices/{device}/pings', [\App\Http\Controllers\UserDevicePingController::class, 'index'])->name('trader.devices.pings');

        Route::post('/payment-details/{paymentDetail}/archive', [\App\Http\Controllers\PaymentDetailArchiveController::class, 'store'])->name('payment-details.archive');
        Route::delete('/payment-details/{paymentDetail}/unarchive', [\App\Http\Controllers\PaymentDetailArchiveController::class, 'destroy'])->name('payment-details.unarchive');
        Route::patch('/payment-details/{paymentDetail}/toggle-active', [\App\Http\Controllers\PaymentDetailController::class, 'toggleActive'])->name('payment-details.unarchive');
        Route::patch('/payment-details/{paymentDetail}/toggle-active', [\App\Http\Controllers\PaymentDetailController::class, 'toggleActive'])->name('payment-details.toggle-active');
        Route::patch('/payment-details/bulk-update', [\App\Http\Controllers\PaymentDetailController::class, 'bulkUpdate'])->name('payment-details.bulk-update');
        Route::resource('/payment-details', \App\Http\Controllers\PaymentDetailController::class)->only(['index', 'store', 'update']);
        Route::get('/payment-details/create-data', [\App\Http\Controllers\PaymentDetailController::class, 'createData'])->name('payment-details.create-data');
        Route::get('/payment-details/{paymentDetail}', [\App\Http\Controllers\PaymentDetailController::class, 'show'])->name('payment-details.show');
        Route::patch('/payment-details/{paymentDetail}/tags', [\App\Http\Controllers\PaymentDetailTagAssignmentController::class, 'update'])->name('payment-details.tags.update');
        Route::post('/payment-detail-tags', [\App\Http\Controllers\PaymentDetailTagController::class, 'store'])->name('payment-detail-tags.store');
        Route::patch('/payment-detail-tags/{paymentDetailTag}', [\App\Http\Controllers\PaymentDetailTagController::class, 'update'])->name('payment-detail-tags.update');
        Route::delete('/payment-detail-tags/{paymentDetailTag}', [\App\Http\Controllers\PaymentDetailTagController::class, 'destroy'])->name('payment-detail-tags.destroy');

        //orders
        Route::resource('/orders', \App\Http\Controllers\OrderController::class)->only(['index']);
        Route::patch('/orders/{order}/accept', [\App\Http\Controllers\OrderController::class, 'acceptOrder'])->name('orders.accept');
        Route::patch('/orders/{order}/amount', [\App\Http\Controllers\Admin\OrderController::class, 'updateAmount'])->name('orders.update.amount');

        //statistics
        Route::get('trader/statistics', [\App\Http\Controllers\Trader\StatisticController::class, 'index'])->name('trader.statistics.index');

        //disputes
        Route::get('/disputes', [\App\Http\Controllers\DisputeController::class, 'index'])->name('disputes.index');
        Route::patch('/disputes/{dispute}/accept', [\App\Http\Controllers\DisputeController::class, 'accept'])->name('disputes.accept');
        Route::patch('/disputes/{dispute}/cancel', [\App\Http\Controllers\DisputeController::class, 'cancel'])->name('disputes.cancel');
        Route::patch('/disputes/{dispute}/rollback', [\App\Http\Controllers\DisputeController::class, 'rollback'])->name('disputes.rollback');

        //app
        Route::get('/bridge.apk', [\App\Http\Controllers\ApkController::class, 'download'])->name('app.download');

        Route::get('/finances', [\App\Http\Controllers\WalletController::class, 'index'])->name('wallet.index');


        Route::get('/sms-logs', [\App\Http\Controllers\SmsLogController::class, 'index'])->name('sms-logs.index');



        // Создание инвойса для пополнения через внешний сервис
        Route::post('/trader/deposit/invoices', [\App\Http\Controllers\Trader\DepositInvoiceController::class, 'store'])->name('trader.deposit.invoices.store');

        //export
        Route::get('/trader/export/orders', [\App\Http\Controllers\Trader\ExportController::class, 'exportOrders'])->name('trader.export.orders');

        //Route::get('/trader/settings', [\App\Http\Controllers\Trader\SettingController::class, 'index'])->name('trader.settings.index');
        //Route::patch('/trader/settings', [\App\Http\Controllers\Trader\SettingController::class, 'update'])->name('trader.settings.update');
    });

    // Группа маршрутов для Support
    Route::group(['prefix' => 'support', 'as'=>'support.', 'middleware' => ['auth', 'banned', 'role:Support|Super Admin']], function () {
        Route::get('/users', [\App\Http\Controllers\Support\UserController::class, 'index'])->name('users.index');
        Route::patch('/users/{user}/toggle-traffic', [\App\Http\Controllers\Support\UserController::class, 'toggleTraffic'])->name('users.toggle-traffic');
        Route::get('/orders', [\App\Http\Controllers\Support\OrderController::class, 'index'])->name('orders.index');
        Route::get('/disputes', [\App\Http\Controllers\Support\DisputeController::class, 'index'])->name('disputes.index');
    });

    //common
    Route::group(['middleware' => ['auth', 'banned', 'role:Trader|Super Admin']], function () {
        Route::get('/modal/sms-logs/{user}', [\App\Http\Controllers\ModalController::class, 'smsLogs'])->name('modal.user.sms-logs');
    });

    Route::group(['middleware' => ['auth', 'banned', 'role:Merchant|Super Admin']], function () {
        // Новые маршруты для управления саппортами
        Route::get('/merchant/support', [\App\Http\Controllers\Merchant\Support\SupportController::class, 'index'])->name('merchant.support.index');
        Route::get('/merchant/support/create-data', [\App\Http\Controllers\Merchant\Support\SupportController::class, 'createData'])->name('merchant.support.create-data');
        Route::post('/merchant/support', [\App\Http\Controllers\Merchant\Support\SupportController::class, 'store'])->name('merchant.support.store');
        Route::get('/merchant/support/{support}/edit-data', [\App\Http\Controllers\Merchant\Support\SupportController::class, 'editData'])->name('merchant.support.edit-data');
        Route::patch('/merchant/support/{support}', [\App\Http\Controllers\Merchant\Support\SupportController::class, 'update'])->name('merchant.support.update');

        Route::get('/merchant/main', [\App\Http\Controllers\MainPageController::class, 'merchant'])->name('merchant.main.index');

        Route::resource('/merchants', \App\Http\Controllers\MerchantController::class)->only(['index', 'store']);
        Route::get('/merchants/data', [\App\Http\Controllers\MerchantController::class, 'indexData'])->name('merchants.data');
        Route::get('/merchants/{merchant}/settings', [\App\Http\Controllers\MerchantController::class, 'settings'])->name('merchants.settings');
        Route::patch('/merchants/{merchant}/callback', [\App\Http\Controllers\MerchantController::class, 'updateCallbackURL'])->name('merchants.callback.update');
        Route::patch('/merchants/{merchant}/gateway-settings', [\App\Http\Controllers\MerchantController::class, 'updateGatewaySettings'])->name('merchants.gateway-settings.update');

        Route::get('/merchant/finances', [\App\Http\Controllers\WalletController::class, 'index'])->name('merchant.finances.index');

        Route::get('/merchant/payouts', [\App\Http\Controllers\Merchant\PayoutController::class, 'index'])->name('merchant.payouts.index');
        Route::get('/merchant/payouts/create-data', [\App\Http\Controllers\Merchant\PayoutController::class, 'createData'])->name('merchant.payouts.create-data');
        Route::post('/merchant/payouts', [\App\Http\Controllers\Merchant\PayoutController::class, 'store'])->name('merchant.payouts.store');
        Route::post('/merchant/payouts/{payout:uuid}/callback/resend', [\App\Http\Controllers\Merchant\PayoutCallbackController::class, 'resend'])->name('merchant.payouts.callback.resend');

        Route::resource('/payments', \App\Http\Controllers\PaymentController::class)->only(['index', 'store']);
        Route::get('/payments/create-data', [\App\Http\Controllers\PaymentController::class, 'createData'])->name('payments.create-data');


        Route::post('/payment/{order}/callback/resend', [\App\Http\Controllers\Merchant\ResendCallbackController::class, 'resend'])->name('payment.callback.resend');
    });

    Route::group(['middleware' => ['auth', 'banned', 'role:Merchant|Merchant Support|Super Admin']], function () {
        Route::get('/integration', [\App\Http\Controllers\ApiIntegrationController::class, 'index'])->name('integration.index');
        Route::get('/integration/receipt-template', [\App\Http\Controllers\ApiIntegrationController::class, 'receiptTemplate'])->name('integration.receipt-template');
        Route::post('/integration/regenerate-token', [\App\Http\Controllers\ApiIntegrationController::class, 'regenerateToken'])
            ->name('integration.regenerate-token');
    });

    Route::group(['prefix' => 'admin', 'as'=>'admin.', 'middleware' => ['auth', 'banned', 'role:Super Admin']], function () {
        Route::get('/main', [\App\Http\Controllers\MainPageController::class, 'admin'])->name('main.index');

        Route::get('/notifications', [\App\Http\Controllers\Admin\NotificationController::class, 'index'])->name('notifications.index');

        Route::get('/app', [\App\Http\Controllers\Admin\ApkController::class, 'index'])->name('app.index');
        Route::post('/app', [\App\Http\Controllers\Admin\ApkController::class, 'store'])->name('app.store');
        Route::get('/anti-fraud/settings', [\App\Http\Controllers\Admin\AntiFraudSettingController::class, 'index'])->name('anti-fraud.settings.index');
        Route::post('/anti-fraud/settings', [\App\Http\Controllers\Admin\AntiFraudSettingController::class, 'store'])->name('anti-fraud.settings.store');
        Route::patch('/anti-fraud/settings/{anti_fraud_setting}', [\App\Http\Controllers\Admin\AntiFraudSettingController::class, 'update'])->name('anti-fraud.settings.update');
        Route::delete('/anti-fraud/settings/{anti_fraud_setting}', [\App\Http\Controllers\Admin\AntiFraudSettingController::class, 'destroy'])->name('anti-fraud.settings.destroy');
        Route::get('/anti-fraud/history', [\App\Http\Controllers\Admin\AntiFraudHistoryController::class, 'index'])->name('anti-fraud.history.index');
        Route::get('/anti-fraud/clients', [\App\Http\Controllers\Admin\AntiFraudClientController::class, 'index'])->name('anti-fraud.clients.index');
        Route::get('/anti-fraud/clients/{merchantClient}/orders', [\App\Http\Controllers\Admin\AntiFraudClientController::class, 'orders'])->name('anti-fraud.clients.orders');
        Route::get('/profit-calculator', [\App\Http\Controllers\Admin\ProfitCalculatorController::class, 'index'])->name('profit-calculator.index');
        Route::post('/profit-calculator/calculate', [\App\Http\Controllers\Admin\ProfitCalculatorController::class, 'calculate'])->name('profit-calculator.calculate');

        Route::get('/enabled-cards', [\App\Http\Controllers\Admin\EnabledCardsController::class, 'index'])->name('enabled-cards.index');

        // Маршруты для фильтрации
        Route::get('/filters/detail-types', [\App\Http\Controllers\Admin\FilterController::class, 'getDetailTypes']);
        Route::get('/filters/payment-gateways', [\App\Http\Controllers\Admin\FilterController::class, 'searchPaymentGateways']);
        Route::get('/filters/users', [\App\Http\Controllers\Admin\FilterController::class, 'searchUsers']);

        Route::patch('/users/{user}/toggle-online', [\App\Http\Controllers\Admin\UserController::class, 'toggleOnline'])->name('users.toggle-online');
        Route::get('/users/roles', [\App\Http\Controllers\Admin\UserController::class, 'roles'])->name('users.roles');
        Route::get('/users/team-leaders', [\App\Http\Controllers\Admin\UserController::class, 'teamLeaders'])->name('users.team-leaders');
        Route::get('/users/{user}/temp-vip-history', [\App\Http\Controllers\Admin\UserController::class, 'tempVipHistory'])->name('users.temp-vip-history');
        Route::get('/users/{user}', [\App\Http\Controllers\Admin\UserController::class, 'show'])->name('users.show');
        Route::resource('/users', \App\Http\Controllers\Admin\UserController::class)->only(['index', 'store', 'update']);
        Route::delete('/users/{user}/reset-2fa', [\App\Http\Controllers\Admin\UserController::class, 'reset2fa'])->name('users.reset-2fa');
        Route::get('/payment-gateways', [\App\Http\Controllers\Admin\PaymentGatewayController::class, 'index'])->name('payment-gateways.index');
        Route::get('/payment-gateways/create-data', [\App\Http\Controllers\Admin\PaymentGatewayController::class, 'createData'])->name('payment-gateways.create-data');
        Route::get('/payment-gateways/bulk-settings-data', [\App\Http\Controllers\Admin\PaymentGatewayController::class, 'bulkSettingsData'])->name('payment-gateways.bulk-settings-data');
        Route::post('/payment-gateways', [\App\Http\Controllers\Admin\PaymentGatewayController::class, 'store'])->name('payment-gateways.store');
        Route::get('/payment-gateways/{paymentGateway}/edit-data', [\App\Http\Controllers\Admin\PaymentGatewayController::class, 'editData'])->name('payment-gateways.edit-data');
        Route::patch('/payment-gateways/bulk-settings', [\App\Http\Controllers\Admin\PaymentGatewayController::class, 'bulkUpdate'])->name('payment-gateways.bulk-settings.update');
        Route::patch('/payment-gateways/{paymentGateway}', [\App\Http\Controllers\Admin\PaymentGatewayController::class, 'update'])->name('payment-gateways.update');
        Route::get('/orders', [\App\Http\Controllers\Admin\OrderController::class, 'index'])->name('orders.index');
        Route::get('/payouts', [\App\Http\Controllers\Admin\PayoutController::class, 'index'])->name('payouts.index');
        Route::patch('/payouts/{payout}/status', [\App\Http\Controllers\Admin\PayoutController::class, 'updateStatus'])->name('payouts.status.update');
        Route::get('/payouts/settings-data', [\App\Http\Controllers\Admin\PayoutController::class, 'settingsData'])->name('payouts.settings-data');
        Route::patch('/payouts/settings', [\App\Http\Controllers\Admin\PayoutController::class, 'updateSettings'])->name('payouts.settings.update');

        Route::get('/user-balances', [\App\Http\Controllers\Admin\UserBalanceController::class, 'index'])->name('user-balances.index');

        Route::get('/deposits', [\App\Http\Controllers\Admin\DepositController::class, 'index'])->name('deposits.index');
        Route::get('/withdrawals', [\App\Http\Controllers\Admin\WithdrawalController::class, 'index'])->name('withdrawals.index');
        Route::get('/withdrawals/address/whitelist', [\App\Http\Controllers\Admin\AddressWhitelistController::class, 'index'])->name('withdrawals.address.whitelist.index');
        Route::patch('/withdrawals/{invoice}/success', [\App\Http\Controllers\Admin\WithdrawalController::class, 'success'])->name('withdrawals.success');
        Route::patch('/withdrawals/{invoice}/fail', [\App\Http\Controllers\Admin\WithdrawalController::class, 'fail'])->name('withdrawals.fail');

        Route::resource('/currencies', \App\Http\Controllers\Admin\CurrencyController::class)->only(['index']);
        Route::get('currencies/{currency}/price-parsers/edit-data', [\App\Http\Controllers\Admin\PriceParserController::class, 'editData'])->name('currencies.price-parsers.edit-data');
        Route::patch('currencies/{currency}/price-parsers', [\App\Http\Controllers\Admin\PriceParserController::class, 'update'])->name('currencies.price-parsers.update');

        Route::get('/sms-logs', [\App\Http\Controllers\Admin\SmsLogController::class, 'index'])->name('sms-logs.index');
        Route::post('/sender-stop-list/{smsLog}', [\App\Http\Controllers\Admin\SenderStopListController::class, 'store'])->name('sender-stop-list.store');
        Route::delete('/sender-stop-list/{senderStopList}', [\App\Http\Controllers\Admin\SenderStopListController::class, 'destroy'])->name('sender-stop-list.destroy');
        Route::post('/sms-stop-word', [\App\Http\Controllers\Admin\SmsStopWordController::class, 'store'])->name('sms-stop-word.store');
        Route::delete('/sms-stop-word/{smsStopWord}', [\App\Http\Controllers\Admin\SmsStopWordController::class, 'destroy'])->name('sms-stop-word.destroy');

        Route::get('/payment-details', [\App\Http\Controllers\Admin\PaymentDetailController::class, 'index'])->name('payment-details.index');


        Route::get('/disputes', [\App\Http\Controllers\Admin\DisputeController::class, 'index'])->name('disputes.index');
        Route::post('/disputes/{order}', [\App\Http\Controllers\Admin\DisputeController::class, 'store'])->name('disputes.store');

        Route::get('/users/{user}/wallet', [\App\Http\Controllers\Admin\UserWalletController::class, 'index'])->name('users.wallet.index');
        Route::post('/users/{user}/wallet/deposit', [\App\Http\Controllers\Admin\UserWalletController::class, 'deposit'])->name('users.wallet.deposit');
        Route::post('/users/{user}/wallet/withdraw', [\App\Http\Controllers\Admin\UserWalletController::class, 'withdraw'])->name('users.wallet.withdraw');

        Route::get('/users/{user}/notes', [\App\Http\Controllers\Admin\UserNoteController::class, 'index'])->name('users.notes.index');
        Route::post('/users/{user}/notes', [\App\Http\Controllers\Admin\UserNoteController::class, 'store'])->name('users.notes.store');

        Route::get('/settings', [\App\Http\Controllers\Admin\SettingsController::class, 'index'])->name('settings.index');
        Route::patch('/settings/update/prime-time-bonus', [\App\Http\Controllers\Admin\SettingsController::class, 'updatePrimeTimeBonus'])->name('settings.update.prime-time-bonus');
        Route::patch('/settings/update/support-link', [\App\Http\Controllers\Admin\SettingsController::class, 'updateSupportLink'])->name('settings.update.support-link');
        Route::patch('/settings/update/funds-on-hold', [\App\Http\Controllers\Admin\SettingsController::class, 'updateFundsOnHold'])->name('settings.update.funds-on-hold');
        Route::patch('/settings/update/max-pending-disputes', [\App\Http\Controllers\Admin\SettingsController::class, 'updateMaxPendingDisputes'])->name('settings.update.max-pending-disputes');
        Route::patch('/settings/update/max-rejected-disputes', [\App\Http\Controllers\Admin\SettingsController::class, 'updateMaxRejectedDisputes'])->name('settings.update.max-rejected-disputes');
        Route::patch('/settings/update/temp-vip', [\App\Http\Controllers\Admin\SettingsController::class, 'updateTempVip'])->name('settings.update.temp-vip');
        Route::patch('/settings/update/default-reserve-balance-limit', [\App\Http\Controllers\Admin\SettingsController::class, 'updateDefaultReserveBalanceLimit'])->name('settings.update.default-reserve-balance-limit');


        Route::get('/merchants', [\App\Http\Controllers\Admin\MerchantController::class, 'index'])->name('merchants.index');
        Route::get('/merchants/data', [\App\Http\Controllers\Admin\MerchantController::class, 'indexData'])->name('merchants.data');
        Route::get('/merchants/{merchant}/settings', [\App\Http\Controllers\MerchantController::class, 'settings'])->name('merchants.settings');
        Route::patch('/merchants/{merchant}/ban', [\App\Http\Controllers\Admin\MerchantController::class, 'ban'])->name('merchants.ban');
        Route::patch('/merchants/{merchant}/unban', [\App\Http\Controllers\Admin\MerchantController::class, 'unban'])->name('merchants.unban');
        Route::patch('/merchants/{merchant}/validated', [\App\Http\Controllers\Admin\MerchantController::class, 'validated'])->name('merchants.validated');
        Route::patch('/merchants/{merchant}/settings', [\App\Http\Controllers\Admin\MerchantController::class, 'updateSettings'])->name('merchants.settings.update');
        Route::patch('/merchants/{merchant}/geo', [\App\Http\Controllers\Admin\MerchantController::class, 'updateGeo'])->name('merchants.geo.update');
        Route::post('/merchants/{merchant}/resend-callback', [\App\Http\Controllers\Admin\MerchantResendCallbackController::class, 'resendByDateRange'])->name('merchants.resend-callback');

        //Route::resource('/categories', \App\Http\Controllers\Admin\CategoryController::class);


        // Вход под другим пользователем
        Route::post('/impersonate/{user}', function (\App\Models\User $user) {
            if (auth()->user()->canImpersonate()) {
                auth()->user()->impersonate($user);

                if ($user->google2fa_secret) {
                    session()->put('user_2fa_passed', true);
                }

                return redirect()->route('dashboard');
            }
            return redirect()->back()->with('error', 'Нет прав для входа под другим пользователем');
        })->name('impersonate.start');

        Route::get('/merchant-api-logs', [\App\Http\Controllers\Admin\MerchantApiLogController::class, 'index'])->name('merchant-api-logs.index');
        Route::post('/merchant-api-logs/delete', [\App\Http\Controllers\Admin\MerchantApiLogController::class, 'deleteByDateRange'])->name('merchant-api-logs.delete');
        Route::get('/callback-logs', [\App\Http\Controllers\Admin\CallbackLogController::class, 'index'])->name('callback-logs.index');

        // Только для локальной разработки: страница со всеми компонентами
        if (is_local()) {
            Route::get('/dev/components', function () {
                return \Inertia\Inertia::render('Dev/ComponentsGallery');
            })->name('dev.components');
        }
    });

    // Группа маршрутов для Merchant Support
    Route::group(['prefix' => 'merchant-support', 'as'=>'merchant-support.', 'middleware' => ['auth', 'banned', 'role:Merchant Support|Super Admin']], function () {
        Route::get('/payments', [\App\Http\Controllers\MerchantSupport\PaymentController::class, 'index'])->name('payments.index');
        Route::get('/integration', [\App\Http\Controllers\ApiIntegrationController::class, 'index'])->name('integration.index');
        Route::post('/payment/{order}/callback/resend', [\App\Http\Controllers\Merchant\ResendCallbackController::class, 'resend'])->name('payment.callback.resend');
    });
});


Route::get('/phpinfo', fn () => phpinfo());

require __DIR__.'/auth.php';

