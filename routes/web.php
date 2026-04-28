<?php

use App\Http\Controllers\Admin\AddressWhitelistController;
use App\Http\Controllers\Admin\AntiFraudClientController;
use App\Http\Controllers\Admin\AntiFraudHistoryController;
use App\Http\Controllers\Admin\AntiFraudSettingController;
use App\Http\Controllers\Admin\CallbackLogController;
use App\Http\Controllers\Admin\CascadeDealController;
use App\Http\Controllers\Admin\MerchantCascadeSettingController;
use App\Http\Controllers\Admin\CascadeProviderController;
use App\Http\Controllers\Admin\CascadeProviderLogController;
use App\Http\Controllers\Admin\CascadeProviderWalletController;
use App\Http\Controllers\Admin\CurrencyController;
use App\Http\Controllers\Admin\IntegrationApiController;
use App\Http\Controllers\Admin\ManualControlAcqController;
use App\Http\Controllers\Admin\MerchantApiLogController;
use App\Http\Controllers\Admin\MerchantResendCallbackController;
use App\Http\Controllers\Admin\NotificationController as AdminNotificationController;
use App\Http\Controllers\Admin\PaymentGatewayController;
use App\Http\Controllers\Admin\PriceParserController;
use App\Http\Controllers\Admin\ProfitCalculatorController;
use App\Http\Controllers\Admin\SenderStopListController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\SmsStopWordController;
use App\Http\Controllers\Admin\UserNoteController;
use App\Http\Controllers\Admin\UserTeamController;
use App\Http\Controllers\Admin\UserWalletController;
use App\Http\Controllers\Admin\WithdrawalController;
use App\Http\Controllers\Analyst\DepositController as AnalystDepositController;
use App\Http\Controllers\Analyst\DisputeController as AnalystDisputeController;
use App\Http\Controllers\Analyst\EnabledCardsController as AnalystEnabledCardsController;
use App\Http\Controllers\Analyst\FilterController as AnalystFilterController;
use App\Http\Controllers\Analyst\OrderController as AnalystOrderController;
use App\Http\Controllers\Analyst\PayoutController as AnalystPayoutController;
use App\Http\Controllers\Analyst\TraderAnalyticsController as AnalystTraderAnalyticsController;
use App\Http\Controllers\Analyst\UserController as AnalystUserController;
use App\Http\Controllers\ApiIntegrationController;
use App\Http\Controllers\ApkController;
use App\Http\Controllers\AppHomeController;
use App\Http\Controllers\DisputeController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\LandingPageController;
use App\Http\Controllers\MainPageController;
use App\Http\Controllers\Merchant\PayoutCallbackController;
use App\Http\Controllers\Merchant\ResendCallbackController;
use App\Http\Controllers\MerchantController;
use App\Http\Controllers\ModalController;
use App\Http\Controllers\NewsController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\NotificationRuleController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PaymentDemoController;
use App\Http\Controllers\PaymentDetailArchiveController;
use App\Http\Controllers\PaymentDetailController;
use App\Http\Controllers\PaymentDetailTagAssignmentController;
use App\Http\Controllers\PaymentDetailTagController;
use App\Http\Controllers\PaymentLinkController;
use App\Http\Controllers\PayoutReceiptController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProviderLiquidity\DashboardController as ProviderLiquidityDashboardController;
use App\Http\Controllers\SmsLogController;
use App\Http\Controllers\Support\DepositController;
use App\Http\Controllers\Support\EnabledCardsController;
use App\Http\Controllers\Support\FilterController;
use App\Http\Controllers\Support\TraderAnalyticsController;
use App\Http\Controllers\Support\UserController;
use App\Http\Controllers\TeamLeader\ReferralController;
use App\Http\Controllers\TeamLeader\TraderController;
use App\Http\Controllers\TeamLeader\TraderDisputeController;
use App\Http\Controllers\TeamLeader\TraderFinanceController;
use App\Http\Controllers\TeamLeader\TraderOrderController;
use App\Http\Controllers\TeamLeader\TraderPaymentDetailController;
use App\Http\Controllers\TelegramSettingsController;
use App\Http\Controllers\TelegramWebhookController;
use App\Http\Controllers\Trader\DepositInvoiceController;
use App\Http\Controllers\Trader\EconomyController;
use App\Http\Controllers\Trader\ExportController;
use App\Http\Controllers\Trader\NotificationController as TraderNotificationController;
use App\Http\Controllers\Trader\PayoutController;
use App\Http\Controllers\Trader\TempVipController;
use App\Http\Controllers\Trader\TraderLeaderboardController;
use App\Http\Controllers\UserDeviceController;
use App\Http\Controllers\UserDevicePingController;
use App\Http\Controllers\UserOnlineController;
use App\Http\Controllers\WalletController;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/payment/demo', [PaymentDemoController::class, 'show'])
    ->middleware('payment.domain')
    ->name('payment.demo.show');
Route::post('/payment/demo/dispute', [PaymentDemoController::class, 'storeDispute'])
    ->middleware('payment.domain')
    ->name('payment.demo.dispute.store');
Route::post('/payment/demo/payment-detail/{paymentGateway}', [PaymentDemoController::class, 'storePaymentDetail'])
    ->middleware('payment.domain')
    ->name('payment.demo.payment-detail.store');
Route::get('/payment/{order:uuid}', [PaymentLinkController::class, 'show'])
    ->middleware('payment.domain')
    ->name('payment.show');
Route::post('/payment/{order:uuid}/dispute', [PaymentLinkController::class, 'storeDispute'])
    ->middleware('payment.domain')
    ->name('payment.dispute.store');
Route::post('/payment/{order:uuid}/payment-detail/{paymentGateway}', [PaymentLinkController::class, 'storePaymentDetail'])
    ->middleware('payment.domain')
    ->name('payment.payment-detail.store');

if (config('domains.split_marketing')) {
    $marketing_host = config('domains.marketing_host');
    if (is_string($marketing_host) && $marketing_host !== '') {
        Route::domain($marketing_host)->middleware(['2fa'])->group(function () {
            Route::get('/', [LandingPageController::class, 'show'])->name('landing.home');
        });
    }
}

Route::post('/telegram/webhook', TelegramWebhookController::class)
    ->middleware(['telegram.secret', 'backoffice.domain'])
    ->name('telegram.webhook');

// Выход из режима Impersonate
Route::post('/impersonate/leave', function () {
    $currentUser = request()->user();

    if ($currentUser?->isImpersonated()) {
        $currentUser->leaveImpersonation();

        return redirect()->route('admin.users.index');
    }

    return redirect()->back()->with('error', 'Вы не в режиме Impersonate');
})->middleware('auth', 'banned', 'backoffice.domain')->name('impersonate.leave');

Route::group(['middleware' => ['backoffice.domain', '2fa']], function () {
    Route::get('/', config('domains.split_marketing')
        ? AppHomeController::class
        : [LandingPageController::class, 'show']
    )->name('dashboard');

    Route::group(['middleware' => ['auth', 'banned']], function () {
        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::patch('/profile/avatar', [ProfileController::class, 'updateAvatar'])->name('profile.update.avatar');
        Route::patch('/profile/auth2fa', [ProfileController::class, 'updateAuth2fa'])->name('profile.update.auth2fa');
        Route::patch('/wallet/fiat-currency', [WalletController::class, 'updateFiatCurrency'])->name('wallet.fiat-currency.update');
    });

    Route::group(['middleware' => ['auth', 'banned']], function () {
        Route::post('/invoice', [InvoiceController::class, 'store'])->name('invoice.store');
        Route::patch('/user/online', [UserOnlineController::class, 'toggle'])->name('user.online.toggle');
        Route::get('/payouts/{payout:uuid}/receipt', [PayoutReceiptController::class, 'show'])->name('payouts.receipts.show');
        Route::get('/payouts/{payout:uuid}/receipts/{receipt}', [PayoutReceiptController::class, 'showItem'])->name('payouts.receipts.item.show');
    });

    Route::group(['middleware' => ['auth', 'banned', 'role:Trader|Merchant|Super Admin']], function () {
        Route::get('/notifications/ping', [NotificationController::class, 'ping'])->name('notifications.ping');
        Route::patch('/notifications/sound-settings', [NotificationController::class, 'updateSoundSettings'])->name('notifications.sound.update');
        Route::post('/notifications/rules', [NotificationRuleController::class, 'store'])->name('notifications.rules.store');
        Route::patch('/notifications/rules/{notificationRule}', [NotificationRuleController::class, 'update'])->name('notifications.rules.update');
        Route::delete('/notifications/rules/{notificationRule}', [NotificationRuleController::class, 'destroy'])->name('notifications.rules.destroy');
        Route::post('/notifications/telegram/link', [TelegramSettingsController::class, 'refreshLink'])->name('notifications.telegram.link');
        Route::post('/notifications/telegram/unlink', [TelegramSettingsController::class, 'unlink'])->name('notifications.telegram.unlink');
    });

    Route::group(['prefix' => 'leader', 'as' => 'leader.',  'middleware' => ['auth', 'banned', 'role:Team Leader|Super Admin']], function () {
        Route::get('/main', [MainPageController::class, 'leader'])->name('main.index');
        Route::get('/news', [NewsController::class, 'index'])->name('news.index');
        Route::get('/finances', [WalletController::class, 'index'])->name('finances.index');
        Route::get('/referrals', [ReferralController::class, 'index'])->name('referrals.index');
        Route::get('/traders', [TraderController::class, 'index'])->name('traders.index');
        Route::patch('/traders/{trader}/toggle-online', [TraderController::class, 'toggleOnline'])->name('traders.toggle-online');
        Route::patch('/traders/{trader}/commission', [TraderController::class, 'updateCommission'])->name('traders.update-commission');
        Route::get('/traders/{trader}', [TraderController::class, 'show'])->name('traders.show');
        Route::get('/traders/{trader}/payment-details', [TraderPaymentDetailController::class, 'index'])->name('traders.payment-details.index');
        Route::get('/traders/{trader}/orders', [TraderOrderController::class, 'index'])->name('traders.orders.index');
        Route::get('/traders/{trader}/disputes', [TraderDisputeController::class, 'index'])->name('traders.disputes.index');
        Route::get('/traders/{trader}/finances', [TraderFinanceController::class, 'index'])->name('traders.finances.index');
    });

    Route::group(['prefix' => 'provider-liquidity', 'as' => 'provider-liquidity.', 'middleware' => ['auth', 'banned', 'role:Provider Liquidity|Super Admin']], function () {
        Route::get('/main', [MainPageController::class, 'providerLiquidity'])->name('main.index');
        Route::get('/services', [ProviderLiquidityDashboardController::class, 'services'])->name('services.index');
        Route::get('/deals', [ProviderLiquidityDashboardController::class, 'deals'])->name('deals.index');
        Route::get('/wallet', [WalletController::class, 'index'])->name('wallet.index');
        Route::get('/logs', [ProviderLiquidityDashboardController::class, 'logs'])->name('logs.index');
    });

    Route::group(['middleware' => ['auth', 'banned', 'role:Trader|Support|Analyst|Super Admin']], function () {
        Route::resource('/orders', OrderController::class)->only(['show']);
        Route::get('/disputes/{dispute}/receipt', [DisputeController::class, 'receipt'])->name('disputes.receipt');
    });

    Route::group(['middleware' => ['auth', 'banned', 'role:Trader|Support|Analyst|Team Leader|Super Admin']], function () {
        Route::post('/news/views', [NewsController::class, 'trackViews'])->name('news.views.store');
        Route::post('/news/reactions', [NewsController::class, 'react'])->name('news.reactions.store');
    });

    Route::group(['middleware' => ['auth', 'banned', 'role:Trader|Super Admin']], function () {
        Route::get('/trader/main', [MainPageController::class, 'trader'])->name('trader.main.index');
        Route::get('/news', [NewsController::class, 'index'])->name('news.index');
        Route::get('/trader/main/filter-options/{type}', [MainPageController::class, 'traderFilterOptions'])->name('trader.main.filter-options');
        Route::post('/trader/temp-vip/activate', [TempVipController::class, 'activate'])->name('trader.temp-vip.activate');

        Route::get('/trader/economy', [EconomyController::class, 'index'])->name('trader.economy.index');
        Route::post('/trader/economy', [EconomyController::class, 'store'])->name('trader.economy.store');
        Route::delete('/trader/economy/{month}', [EconomyController::class, 'destroy'])->name('trader.economy.destroy');
        Route::patch('/trader/economy/{month}/days/{day}', [EconomyController::class, 'updateDay'])
            ->whereNumber('day')
            ->name('trader.economy.days.update');

        Route::get('/notifications', [TraderNotificationController::class, 'index'])->name('notifications.index');

        // payouts
        Route::get('/trader/payouts', [PayoutController::class, 'index'])->name('trader.payouts.index');
        Route::post('/trader/payouts/{payout:uuid}/take', [PayoutController::class, 'take'])->name('trader.payouts.take');
        Route::post('/trader/payouts/{payout:uuid}/mark-sent', [PayoutController::class, 'markSent'])->name('trader.payouts.mark-sent');

        // Маршруты для управления устройствами
        Route::get('/trader/devices', [UserDeviceController::class, 'index'])->name('trader.devices.index');
        Route::post('/trader/devices', [UserDeviceController::class, 'store'])->name('trader.devices.store');
        Route::patch('/trader/devices/sms-processing-mode', [UserDeviceController::class, 'updateSmsProcessingMode'])
            ->name('trader.devices.sms-processing-mode.update');
        Route::get('/trader/devices/{device}/pings', [UserDevicePingController::class, 'index'])->name('trader.devices.pings');

        Route::post('/payment-details/{paymentDetail}/archive', [PaymentDetailArchiveController::class, 'store'])->name('payment-details.archive');
        Route::delete('/payment-details/{paymentDetail}/unarchive', [PaymentDetailArchiveController::class, 'destroy'])->name('payment-details.unarchive');
        Route::patch('/payment-details/{paymentDetail}/toggle-active', [PaymentDetailController::class, 'toggleActive'])->name('payment-details.unarchive');
        Route::patch('/payment-details/{paymentDetail}/toggle-active', [PaymentDetailController::class, 'toggleActive'])->name('payment-details.toggle-active');
        Route::patch('/payment-details/bulk-update', [PaymentDetailController::class, 'bulkUpdate'])->name('payment-details.bulk-update');
        Route::resource('/payment-details', PaymentDetailController::class)->only(['index', 'store', 'update']);
        Route::get('/payment-details/create-data', [PaymentDetailController::class, 'createData'])->name('payment-details.create-data');
        Route::get('/payment-details/{paymentDetail}', [PaymentDetailController::class, 'show'])->name('payment-details.show');
        Route::patch('/payment-details/{paymentDetail}/tags', [PaymentDetailTagAssignmentController::class, 'update'])->name('payment-details.tags.update');
        Route::post('/payment-detail-tags', [PaymentDetailTagController::class, 'store'])->name('payment-detail-tags.store');
        Route::patch('/payment-detail-tags/{paymentDetailTag}', [PaymentDetailTagController::class, 'update'])->name('payment-detail-tags.update');
        Route::delete('/payment-detail-tags/{paymentDetailTag}', [PaymentDetailTagController::class, 'destroy'])->name('payment-detail-tags.destroy');

        // orders
        Route::resource('/orders', OrderController::class)->only(['index']);
        Route::patch('/orders/{order}/accept', [OrderController::class, 'acceptOrder'])->name('orders.accept');
        Route::patch('/orders/{order}/amount', [App\Http\Controllers\Admin\OrderController::class, 'updateAmount'])->name('orders.update.amount');

        Route::get('/trader/leaderboard', [TraderLeaderboardController::class, 'index'])->name('trader.leaderboard.index');
        Route::patch('/trader/leaderboard/hide-name', [TraderLeaderboardController::class, 'updateHideName'])->name('trader.leaderboard.hide-name.update');

        // disputes
        Route::get('/disputes', [DisputeController::class, 'index'])->name('disputes.index');
        Route::patch('/disputes/{dispute}/accept', [DisputeController::class, 'accept'])->name('disputes.accept');
        Route::patch('/disputes/{dispute}/cancel', [DisputeController::class, 'cancel'])->name('disputes.cancel');
        Route::patch('/disputes/{dispute}/rollback', [DisputeController::class, 'rollback'])->name('disputes.rollback');

        // app
        Route::get('/bridge.apk', [ApkController::class, 'download'])->name('app.download');

        Route::get('/finances', [WalletController::class, 'index'])->name('wallet.index');

        Route::get('/sms-logs', [SmsLogController::class, 'index'])->name('sms-logs.index');

        // Создание инвойса для пополнения через внешний сервис
        Route::post('/trader/deposit/invoices', [DepositInvoiceController::class, 'store'])->name('trader.deposit.invoices.store');

        // export
        Route::get('/trader/export/orders', [ExportController::class, 'exportOrders'])->name('trader.export.orders');
        Route::get('/trader/export/payouts', [ExportController::class, 'exportPayouts'])->name('trader.export.payouts');

        // Route::get('/trader/settings', [\App\Http\Controllers\Trader\SettingController::class, 'index'])->name('trader.settings.index');
        // Route::patch('/trader/settings', [\App\Http\Controllers\Trader\SettingController::class, 'update'])->name('trader.settings.update');
    });

    // Группа маршрутов для Support
    Route::group(['prefix' => 'support', 'as' => 'support.', 'middleware' => ['auth', 'banned', 'role:Support|Super Admin']], function () {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/news', [NewsController::class, 'index'])->name('news.index');
        Route::patch('/users/{user}/toggle-traffic', [UserController::class, 'toggleTraffic'])->name('users.toggle-traffic');
        Route::get('/enabled-cards', [EnabledCardsController::class, 'index'])->name('enabled-cards.index');
        Route::post('/enabled-cards/limit-levels', [EnabledCardsController::class, 'storeLimitLevel'])->name('enabled-cards.limit-levels.store');
        Route::delete('/enabled-cards/limit-levels', [EnabledCardsController::class, 'destroyLimitLevel'])->name('enabled-cards.limit-levels.destroy');
        Route::get('/traders/analytics', [TraderAnalyticsController::class, 'index'])->name('traders-analytics.index');
        Route::patch('/traders/analytics/operations-threshold', [TraderAnalyticsController::class, 'updateOperationsThreshold'])->name('traders-analytics.operations-threshold.update');
        Route::get('/traders/analytics/traders/search', [TraderAnalyticsController::class, 'searchTraders'])->name('traders-analytics.traders.search');
        Route::get('/orders', [App\Http\Controllers\Support\OrderController::class, 'index'])->name('orders.index');
        Route::patch('/orders/{order}/accept', [App\Http\Controllers\Support\OrderController::class, 'acceptOrder'])->name('orders.accept');
        Route::patch('/orders/{order}/amount', [App\Http\Controllers\Support\OrderController::class, 'updateAmount'])->name('orders.update.amount');
        Route::get('/manual-control-acq', [ManualControlAcqController::class, 'show'])->name('manual-control-acq.show');
        Route::get('/manual-control-acq/state', [ManualControlAcqController::class, 'state'])->name('manual-control-acq.state');
        Route::post('/manual-control-acq/work-status', [ManualControlAcqController::class, 'setWorkStatus'])->name('manual-control-acq.work-status');
        Route::patch('/manual-control-acq/sound-settings', [ManualControlAcqController::class, 'updateSoundSettings'])->name('manual-control-acq.sound-settings.update');
        Route::post('/manual-control-acq/orders/{order}/take', [ManualControlAcqController::class, 'take'])->name('manual-control-acq.take');
        Route::post('/manual-control-acq/orders/{order}/confirmation-type', [ManualControlAcqController::class, 'setConfirmationType'])->name('manual-control-acq.set-confirmation-type');
        Route::post('/manual-control-acq/orders/{order}/confirm', [ManualControlAcqController::class, 'confirm'])->name('manual-control-acq.confirm');
        Route::post('/manual-control-acq/orders/{order}/reject', [ManualControlAcqController::class, 'reject'])->name('manual-control-acq.reject');
        Route::get('/deposits', [DepositController::class, 'index'])->name('deposits.index');
        Route::get('/disputes', [App\Http\Controllers\Support\DisputeController::class, 'index'])->name('disputes.index');
        Route::post('/disputes/{order}', [App\Http\Controllers\Support\DisputeController::class, 'store'])->name('disputes.store');
        Route::patch('/disputes/{dispute}/accept', [App\Http\Controllers\Support\DisputeController::class, 'accept'])->name('disputes.accept');
        Route::patch('/disputes/{dispute}/cancel', [App\Http\Controllers\Support\DisputeController::class, 'cancel'])->name('disputes.cancel');
        Route::patch('/disputes/{dispute}/rollback', [App\Http\Controllers\Support\DisputeController::class, 'rollback'])->name('disputes.rollback');
        Route::get('/payouts', [App\Http\Controllers\Support\PayoutController::class, 'index'])->name('payouts.index');

        // Маршруты для фильтрации
        Route::get('/filters/detail-types', [FilterController::class, 'getDetailTypes']);
        Route::get('/filters/payment-gateways', [FilterController::class, 'searchPaymentGateways']);
        Route::get('/filters/users', [FilterController::class, 'searchUsers']);
    });

    Route::group(['prefix' => 'analyst', 'as' => 'analyst.', 'middleware' => ['auth', 'banned', 'role:Analyst|Super Admin']], function () {
        Route::get('/main', [MainPageController::class, 'analyst'])->name('main.index');
        Route::get('/main/filter-options/{type}', [MainPageController::class, 'adminFilterOptions'])->name('main.filter-options');
        Route::get('/merchant-api-logs', [MerchantApiLogController::class, 'index'])->name('merchant-api-logs.index');

        Route::get('/users', [AnalystUserController::class, 'index'])->name('users.index');
        Route::get('/news', [NewsController::class, 'index'])->name('news.index');
        Route::patch('/users/{user}/toggle-traffic', [AnalystUserController::class, 'toggleTraffic'])->name('users.toggle-traffic');
        Route::get('/enabled-cards', [AnalystEnabledCardsController::class, 'index'])->name('enabled-cards.index');
        Route::post('/enabled-cards/limit-levels', [AnalystEnabledCardsController::class, 'storeLimitLevel'])->name('enabled-cards.limit-levels.store');
        Route::delete('/enabled-cards/limit-levels', [AnalystEnabledCardsController::class, 'destroyLimitLevel'])->name('enabled-cards.limit-levels.destroy');
        Route::get('/traders/analytics', [AnalystTraderAnalyticsController::class, 'index'])->name('traders-analytics.index');
        Route::patch('/traders/analytics/operations-threshold', [AnalystTraderAnalyticsController::class, 'updateOperationsThreshold'])->name('traders-analytics.operations-threshold.update');
        Route::get('/traders/analytics/traders/search', [AnalystTraderAnalyticsController::class, 'searchTraders'])->name('traders-analytics.traders.search');
        Route::get('/orders', [AnalystOrderController::class, 'index'])->name('orders.index');
        Route::patch('/orders/{order}/accept', [AnalystOrderController::class, 'acceptOrder'])->name('orders.accept');
        Route::patch('/orders/{order}/amount', [AnalystOrderController::class, 'updateAmount'])->name('orders.update.amount');
        Route::get('/manual-control-acq', [ManualControlAcqController::class, 'show'])->name('manual-control-acq.show');
        Route::get('/manual-control-acq/state', [ManualControlAcqController::class, 'state'])->name('manual-control-acq.state');
        Route::post('/manual-control-acq/work-status', [ManualControlAcqController::class, 'setWorkStatus'])->name('manual-control-acq.work-status');
        Route::patch('/manual-control-acq/sound-settings', [ManualControlAcqController::class, 'updateSoundSettings'])->name('manual-control-acq.sound-settings.update');
        Route::post('/manual-control-acq/orders/{order}/take', [ManualControlAcqController::class, 'take'])->name('manual-control-acq.take');
        Route::post('/manual-control-acq/orders/{order}/confirmation-type', [ManualControlAcqController::class, 'setConfirmationType'])->name('manual-control-acq.set-confirmation-type');
        Route::post('/manual-control-acq/orders/{order}/confirm', [ManualControlAcqController::class, 'confirm'])->name('manual-control-acq.confirm');
        Route::post('/manual-control-acq/orders/{order}/reject', [ManualControlAcqController::class, 'reject'])->name('manual-control-acq.reject');
        Route::get('/deposits', [AnalystDepositController::class, 'index'])->name('deposits.index');
        Route::get('/disputes', [AnalystDisputeController::class, 'index'])->name('disputes.index');
        Route::post('/disputes/{order}', [AnalystDisputeController::class, 'store'])->name('disputes.store');
        Route::patch('/disputes/{dispute}/accept', [AnalystDisputeController::class, 'accept'])->name('disputes.accept');
        Route::patch('/disputes/{dispute}/cancel', [AnalystDisputeController::class, 'cancel'])->name('disputes.cancel');
        Route::patch('/disputes/{dispute}/rollback', [AnalystDisputeController::class, 'rollback'])->name('disputes.rollback');
        Route::get('/payouts', [AnalystPayoutController::class, 'index'])->name('payouts.index');

        Route::get('/filters/detail-types', [AnalystFilterController::class, 'getDetailTypes']);
        Route::get('/filters/payment-gateways', [AnalystFilterController::class, 'searchPaymentGateways']);
        Route::get('/filters/users', [AnalystFilterController::class, 'searchUsers']);
    });

    // common
    Route::group(['middleware' => ['auth', 'banned', 'role:Trader|Super Admin']], function () {
        Route::get('/modal/sms-logs/{user}', [ModalController::class, 'smsLogs'])->name('modal.user.sms-logs');
    });

    Route::group(['middleware' => ['auth', 'banned', 'role:Merchant|Super Admin']], function () {
        Route::get('/merchant/main', [MainPageController::class, 'merchant'])->name('merchant.main.index');
        Route::get('/merchant/main/filter-options/{type}', [MainPageController::class, 'merchantFilterOptions'])->name('merchant.main.filter-options');

        Route::resource('/merchants', MerchantController::class)->only(['index', 'store']);
        Route::get('/merchants/data', [MerchantController::class, 'indexData'])->name('merchants.data');
        Route::get('/merchants/{merchant}/settings', [MerchantController::class, 'settings'])->name('merchants.settings');
        Route::patch('/merchants/{merchant}/callback', [MerchantController::class, 'updateCallbackURL'])->name('merchants.callback.update');
        Route::patch('/merchants/{merchant}/commission-settings', [MerchantController::class, 'updateCommissionSettings'])->name('merchants.commission-settings.update');

        Route::get('/merchant/finances', [WalletController::class, 'index'])->name('merchant.finances.index');

        Route::get('/merchant/payouts', [App\Http\Controllers\Merchant\PayoutController::class, 'index'])->name('merchant.payouts.index');
        Route::get('/merchant/payouts/create-data', [App\Http\Controllers\Merchant\PayoutController::class, 'createData'])->name('merchant.payouts.create-data');
        Route::post('/merchant/payouts', [App\Http\Controllers\Merchant\PayoutController::class, 'store'])->name('merchant.payouts.store');
        Route::post('/merchant/payouts/{payout:uuid}/callback/resend', [PayoutCallbackController::class, 'resend'])->name('merchant.payouts.callback.resend');

        Route::resource('/payments', PaymentController::class)->only(['index', 'store']);
        Route::get('/payments/create-data', [PaymentController::class, 'createData'])->name('payments.create-data');

        Route::post('/payment/{order}/callback/resend', [ResendCallbackController::class, 'resend'])->name('payment.callback.resend');
    });

    Route::group(['middleware' => ['auth', 'banned', 'role:Merchant|Super Admin']], function () {
        Route::get('/integration', [ApiIntegrationController::class, 'index'])->name('integration.index');
        Route::get('/integration/receipt-template', [ApiIntegrationController::class, 'receiptTemplate'])->name('integration.receipt-template');
        Route::post('/integration/regenerate-token', [ApiIntegrationController::class, 'regenerateToken'])
            ->name('integration.regenerate-token');
    });

    Route::group(['prefix' => 'admin', 'as' => 'admin.', 'middleware' => ['auth', 'banned', 'role:Super Admin']], function () {
        Route::get('/main', [MainPageController::class, 'admin'])->name('main.index');
        Route::get('/manual-control-acq', [ManualControlAcqController::class, 'show'])->name('manual-control-acq.show');
        Route::get('/manual-control-acq/state', [ManualControlAcqController::class, 'state'])->name('manual-control-acq.state');
        Route::post('/manual-control-acq/work-status', [ManualControlAcqController::class, 'setWorkStatus'])->name('manual-control-acq.work-status');
        Route::patch('/manual-control-acq/sound-settings', [ManualControlAcqController::class, 'updateSoundSettings'])->name('manual-control-acq.sound-settings.update');
        Route::post('/manual-control-acq/orders/{order}/take', [ManualControlAcqController::class, 'take'])->name('manual-control-acq.take');
        Route::post('/manual-control-acq/orders/{order}/confirmation-type', [ManualControlAcqController::class, 'setConfirmationType'])->name('manual-control-acq.set-confirmation-type');
        Route::post('/manual-control-acq/orders/{order}/confirm', [ManualControlAcqController::class, 'confirm'])->name('manual-control-acq.confirm');
        Route::post('/manual-control-acq/orders/{order}/reject', [ManualControlAcqController::class, 'reject'])->name('manual-control-acq.reject');
        Route::get('/news', [NewsController::class, 'index'])->name('news.index');
        Route::post('/news', [NewsController::class, 'store'])->name('news.store');
        Route::delete('/news/{newsPost}', [NewsController::class, 'destroy'])->name('news.destroy');
        Route::get('/main/filter-options/{type}', [MainPageController::class, 'adminFilterOptions'])->name('main.filter-options');
        Route::get('/notifications', [AdminNotificationController::class, 'index'])->name('notifications.index');

        Route::get('/app', [App\Http\Controllers\Admin\ApkController::class, 'index'])->name('app.index');
        Route::post('/app', [App\Http\Controllers\Admin\ApkController::class, 'store'])->name('app.store');
        Route::get('/integration-api', [IntegrationApiController::class, 'index'])->name('integration-api.index');
        Route::post('/integration-api/regenerate-token', [IntegrationApiController::class, 'regenerateToken'])->name('integration-api.regenerate-token');
        Route::get('/anti-fraud/settings', [AntiFraudSettingController::class, 'index'])->name('anti-fraud.settings.index');
        Route::post('/anti-fraud/settings', [AntiFraudSettingController::class, 'store'])->name('anti-fraud.settings.store');
        Route::patch('/anti-fraud/settings/{anti_fraud_setting}', [AntiFraudSettingController::class, 'update'])->name('anti-fraud.settings.update');
        Route::delete('/anti-fraud/settings/{anti_fraud_setting}', [AntiFraudSettingController::class, 'destroy'])->name('anti-fraud.settings.destroy');
        Route::get('/anti-fraud/history', [AntiFraudHistoryController::class, 'index'])->name('anti-fraud.history.index');
        Route::get('/anti-fraud/clients', [AntiFraudClientController::class, 'index'])->name('anti-fraud.clients.index');
        Route::get('/anti-fraud/clients/{merchantClient}/orders', [AntiFraudClientController::class, 'orders'])->name('anti-fraud.clients.orders');
        Route::get('/profit-calculator', [ProfitCalculatorController::class, 'index'])->name('profit-calculator.index');
        Route::post('/profit-calculator/calculate', [ProfitCalculatorController::class, 'calculate'])->name('profit-calculator.calculate');

        Route::get('/enabled-cards', [App\Http\Controllers\Admin\EnabledCardsController::class, 'index'])->name('enabled-cards.index');
        Route::post('/enabled-cards/limit-levels', [App\Http\Controllers\Admin\EnabledCardsController::class, 'storeLimitLevel'])->name('enabled-cards.limit-levels.store');
        Route::delete('/enabled-cards/limit-levels', [App\Http\Controllers\Admin\EnabledCardsController::class, 'destroyLimitLevel'])->name('enabled-cards.limit-levels.destroy');
        Route::get('/traders/analytics', [App\Http\Controllers\Admin\TraderAnalyticsController::class, 'index'])->name('traders-analytics.index');
        Route::patch('/traders/analytics/operations-threshold', [App\Http\Controllers\Admin\TraderAnalyticsController::class, 'updateOperationsThreshold'])->name('traders-analytics.operations-threshold.update');
        Route::get('/traders/analytics/traders/search', [App\Http\Controllers\Admin\TraderAnalyticsController::class, 'searchTraders'])->name('traders-analytics.traders.search');

        // Маршруты для фильтрации
        Route::get('/filters/detail-types', [App\Http\Controllers\Admin\FilterController::class, 'getDetailTypes']);
        Route::get('/filters/payment-gateways', [App\Http\Controllers\Admin\FilterController::class, 'searchPaymentGateways']);
        Route::get('/filters/users', [App\Http\Controllers\Admin\FilterController::class, 'searchUsers']);

        Route::patch('/users/{user}/toggle-online', [App\Http\Controllers\Admin\UserController::class, 'toggleOnline'])->name('users.toggle-online');
        Route::post('/users/{user}/archive', [App\Http\Controllers\Admin\UserController::class, 'archive'])->name('users.archive');
        Route::delete('/users/{user}/unarchive', [App\Http\Controllers\Admin\UserController::class, 'unarchive'])->name('users.unarchive');
        Route::patch('/users/{user}/team', [App\Http\Controllers\Admin\UserController::class, 'updateTeam'])->name('users.team.update');
        Route::get('/users/roles', [App\Http\Controllers\Admin\UserController::class, 'roles'])->name('users.roles');
        Route::get('/users/team-leaders', [App\Http\Controllers\Admin\UserController::class, 'teamLeaders'])->name('users.team-leaders');
        Route::get('/users/{user}/temp-vip-history', [App\Http\Controllers\Admin\UserController::class, 'tempVipHistory'])->name('users.temp-vip-history');
        Route::get('/users/{user}', [App\Http\Controllers\Admin\UserController::class, 'show'])->name('users.show');
        Route::resource('/users', App\Http\Controllers\Admin\UserController::class)->only(['index', 'store', 'update']);
        Route::get('/user-teams', [UserTeamController::class, 'index'])->name('user-teams.index');
        Route::post('/user-teams', [UserTeamController::class, 'store'])->name('user-teams.store');
        Route::patch('/user-teams/{userTeam}', [UserTeamController::class, 'update'])->name('user-teams.update');
        Route::delete('/user-teams/{userTeam}', [UserTeamController::class, 'destroy'])->name('user-teams.destroy');
        Route::delete('/users/{user}/reset-2fa', [App\Http\Controllers\Admin\UserController::class, 'reset2fa'])->name('users.reset-2fa');
        Route::get('/payment-gateways', [PaymentGatewayController::class, 'index'])->name('payment-gateways.index');
        Route::get('/payment-gateways/create-data', [PaymentGatewayController::class, 'createData'])->name('payment-gateways.create-data');
        Route::get('/payment-gateways/bulk-settings-data', [PaymentGatewayController::class, 'bulkSettingsData'])->name('payment-gateways.bulk-settings-data');
        Route::post('/payment-gateways', [PaymentGatewayController::class, 'store'])->name('payment-gateways.store');
        Route::get('/payment-gateways/{paymentGateway}/edit-data', [PaymentGatewayController::class, 'editData'])->name('payment-gateways.edit-data');
        Route::patch('/payment-gateways/bulk-settings', [PaymentGatewayController::class, 'bulkUpdate'])->name('payment-gateways.bulk-settings.update');
        Route::patch('/payment-gateways/{paymentGateway}', [PaymentGatewayController::class, 'update'])->name('payment-gateways.update');
        Route::get('/cascade-providers', [CascadeProviderController::class, 'index'])->name('cascade-providers.index');
        Route::post('/cascade-providers', [CascadeProviderController::class, 'store'])->name('cascade-providers.store');
        Route::patch('/cascade-providers/reorder', [CascadeProviderController::class, 'reorder'])->name('cascade-providers.reorder');
        Route::patch('/cascade-providers/{cascadeProvider}', [CascadeProviderController::class, 'update'])->name('cascade-providers.update');
        Route::post('/cascade-providers/{cascadeProvider}/wallet/deposit', [CascadeProviderWalletController::class, 'deposit'])->name('cascade-providers.wallet.deposit');
        Route::post('/cascade-providers/{cascadeProvider}/wallet/withdraw', [CascadeProviderWalletController::class, 'withdraw'])->name('cascade-providers.wallet.withdraw');
        Route::post('/cascade-provider-holds/{fundsOnHold}/release', [CascadeProviderWalletController::class, 'releaseHold'])->name('cascade-provider-holds.release');
        Route::post('/cascade-provider-holds/{fundsOnHold}/reconcile', [CascadeProviderWalletController::class, 'reconcileHold'])->name('cascade-provider-holds.reconcile');
        Route::get('/cascade-deals', [CascadeDealController::class, 'index'])->name('cascade-deals.index');
        Route::get('/cascade-merchant-settings', [MerchantCascadeSettingController::class, 'index'])->name('cascade-merchant-settings.index');
        Route::patch('/cascade-merchant-settings/{merchant}', [MerchantCascadeSettingController::class, 'update'])->name('cascade-merchant-settings.update');
        Route::get('/cascade-provider-logs', [CascadeProviderLogController::class, 'index'])->name('cascade-provider-logs.index');
        Route::get('/orders', [App\Http\Controllers\Admin\OrderController::class, 'index'])->name('orders.index');
        Route::get('/payouts', [App\Http\Controllers\Admin\PayoutController::class, 'index'])->name('payouts.index');
        Route::get('/payouts/export', [App\Http\Controllers\Admin\PayoutController::class, 'export'])->name('payouts.export');
        Route::patch('/payouts/{payout}/status', [App\Http\Controllers\Admin\PayoutController::class, 'updateStatus'])->name('payouts.status.update');
        Route::get('/payouts/settings-data', [App\Http\Controllers\Admin\PayoutController::class, 'settingsData'])->name('payouts.settings-data');
        Route::patch('/payouts/settings', [App\Http\Controllers\Admin\PayoutController::class, 'updateSettings'])->name('payouts.settings.update');

        Route::get('/deposits', [App\Http\Controllers\Admin\DepositController::class, 'index'])->name('deposits.index');
        Route::get('/withdrawals', [WithdrawalController::class, 'index'])->name('withdrawals.index');
        Route::get('/withdrawals/address/whitelist', [AddressWhitelistController::class, 'index'])->name('withdrawals.address.whitelist.index');
        Route::patch('/withdrawals/{invoice}/success', [WithdrawalController::class, 'success'])->name('withdrawals.success');
        Route::patch('/withdrawals/{invoice}/fail', [WithdrawalController::class, 'fail'])->name('withdrawals.fail');

        Route::resource('/currencies', CurrencyController::class)->only(['index']);
        Route::get('currencies/{currency}/price-parsers/edit-data', [PriceParserController::class, 'editData'])->name('currencies.price-parsers.edit-data');
        Route::patch('currencies/{currency}/price-parsers', [PriceParserController::class, 'update'])->name('currencies.price-parsers.update');

        Route::get('/sms-logs', [App\Http\Controllers\Admin\SmsLogController::class, 'index'])->name('sms-logs.index');
        Route::post('/sender-stop-list/{smsLog}', [SenderStopListController::class, 'store'])->name('sender-stop-list.store');
        Route::post('/sender-payment-gateway/{smsLog}', [SenderStopListController::class, 'attachToPaymentGateway'])->name('sender-payment-gateway.store');
        Route::delete('/sender-stop-list/{senderStopList}', [SenderStopListController::class, 'destroy'])->name('sender-stop-list.destroy');
        Route::post('/sms-stop-word', [SmsStopWordController::class, 'store'])->name('sms-stop-word.store');
        Route::delete('/sms-stop-word/{smsStopWord}', [SmsStopWordController::class, 'destroy'])->name('sms-stop-word.destroy');

        Route::get('/payment-details', [App\Http\Controllers\Admin\PaymentDetailController::class, 'index'])->name('payment-details.index');

        Route::get('/disputes', [App\Http\Controllers\Admin\DisputeController::class, 'index'])->name('disputes.index');
        Route::post('/disputes/{order}', [App\Http\Controllers\Admin\DisputeController::class, 'store'])->name('disputes.store');

        Route::get('/users/{user}/wallet', [UserWalletController::class, 'index'])->name('users.wallet.index');
        Route::get('/users/{user}/wallet/transactions/export', [UserWalletController::class, 'exportTransactions'])->name('users.wallet.transactions.export');
        Route::post('/users/{user}/wallet/deposit', [UserWalletController::class, 'deposit'])->name('users.wallet.deposit');
        Route::post('/users/{user}/wallet/withdraw', [UserWalletController::class, 'withdraw'])->name('users.wallet.withdraw');

        Route::get('/users/{user}/notes', [UserNoteController::class, 'index'])->name('users.notes.index');
        Route::post('/users/{user}/notes', [UserNoteController::class, 'store'])->name('users.notes.store');

        Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
        Route::patch('/settings/update/app-slogan', [SettingsController::class, 'updateAppSlogan'])->name('settings.update.app-slogan');
        Route::patch('/settings/update/prime-time-bonus', [SettingsController::class, 'updatePrimeTimeBonus'])->name('settings.update.prime-time-bonus');
        Route::patch('/settings/update/support-link', [SettingsController::class, 'updateSupportLink'])->name('settings.update.support-link');
        Route::patch('/settings/update/landing-telegram-link', [SettingsController::class, 'updateLandingTelegramLink'])->name('settings.update.landing-telegram-link');
        Route::patch('/settings/update/funds-on-hold', [SettingsController::class, 'updateFundsOnHold'])->name('settings.update.funds-on-hold');
        Route::patch('/settings/update/max-pending-disputes', [SettingsController::class, 'updateMaxPendingDisputes'])->name('settings.update.max-pending-disputes');
        Route::patch('/settings/update/max-rejected-disputes', [SettingsController::class, 'updateMaxRejectedDisputes'])->name('settings.update.max-rejected-disputes');
        Route::patch('/settings/update/temp-vip', [SettingsController::class, 'updateTempVip'])->name('settings.update.temp-vip');
        Route::patch('/settings/update/default-reserve-balance-limit', [SettingsController::class, 'updateDefaultReserveBalanceLimit'])->name('settings.update.default-reserve-balance-limit');

        Route::get('/merchants', [App\Http\Controllers\Admin\MerchantController::class, 'index'])->name('merchants.index');
        Route::get('/merchants/data', [App\Http\Controllers\Admin\MerchantController::class, 'indexData'])->name('merchants.data');
        Route::get('/merchants/{merchant}/settings', [MerchantController::class, 'settings'])->name('merchants.settings');
        Route::patch('/merchants/{merchant}/ban', [App\Http\Controllers\Admin\MerchantController::class, 'ban'])->name('merchants.ban');
        Route::patch('/merchants/{merchant}/unban', [App\Http\Controllers\Admin\MerchantController::class, 'unban'])->name('merchants.unban');
        Route::patch('/merchants/{merchant}/validated', [App\Http\Controllers\Admin\MerchantController::class, 'validated'])->name('merchants.validated');
        Route::patch('/merchants/{merchant}/settings', [App\Http\Controllers\Admin\MerchantController::class, 'updateSettings'])->name('merchants.settings.update');
        Route::patch('/merchants/{merchant}/geo', [App\Http\Controllers\Admin\MerchantController::class, 'updateGeo'])->name('merchants.geo.update');
        Route::patch('/merchants/{merchant}/commission-settings', [MerchantController::class, 'updateCommissionSettings'])->name('merchants.commission-settings.update');
        Route::post('/merchants/{merchant}/resend-callback', [MerchantResendCallbackController::class, 'resendByDateRange'])->name('merchants.resend-callback');

        // Route::resource('/categories', \App\Http\Controllers\Admin\CategoryController::class);

        // Вход под другим пользователем
        Route::post('/impersonate/{user}', function (User $user) {
            $currentUser = request()->user();

            if ($currentUser?->canImpersonate()) {
                $currentUser->impersonate($user);

                if ($user->google2fa_secret) {
                    session()->put('user_2fa_passed', true);
                }

                return redirect()->route('dashboard');
            }

            return redirect()->back()->with('error', 'Нет прав для входа под другим пользователем');
        })->name('impersonate.start');

        Route::get('/merchant-api-logs', [MerchantApiLogController::class, 'index'])->name('merchant-api-logs.index');
        Route::post('/merchant-api-logs/delete', [MerchantApiLogController::class, 'deleteByDateRange'])->name('merchant-api-logs.delete');
        Route::get('/callback-logs', [CallbackLogController::class, 'index'])->name('callback-logs.index');

        // Только для локальной разработки: страница со всеми компонентами
        if (is_local()) {
            Route::get('/dev/components', function () {
                return Inertia::render('Dev/ComponentsGallery');
            })->name('dev.components');
        }
    });

});

require __DIR__.'/auth.php';
