<?php

use App\Http\Controllers\Admin\AddressWhitelistController;
use App\Http\Controllers\Admin\AntiFraudClientController;
use App\Http\Controllers\Admin\AntiFraudHistoryController;
use App\Http\Controllers\Admin\AntiFraudSettingController;
use App\Http\Controllers\Admin\DashboardStatsController;
use App\Http\Controllers\Admin\EnabledCardsController;
use App\Http\Controllers\Admin\FinancesController;
use App\Http\Controllers\Admin\IntegrationApiController;
use App\Http\Controllers\Admin\ManualControlAcqController;
use App\Http\Controllers\Admin\MerchantApiLogController;
use App\Http\Controllers\Admin\NotificationController as AdminNotificationController;
use App\Http\Controllers\Admin\OpenAiSettingController;
use App\Http\Controllers\Admin\PaymentGatewayController;
use App\Http\Controllers\Admin\ProfitCalculatorController;
use App\Http\Controllers\Admin\RateSourceController;
use App\Http\Controllers\Admin\SenderStopListController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\SmsStopWordController;
use App\Http\Controllers\Admin\TelegramBotSettingController;
use App\Http\Controllers\Admin\TelegramChatAttachmentController;
use App\Http\Controllers\Admin\TelegramChatController;
use App\Http\Controllers\Admin\TelegramChatTraderController;
use App\Http\Controllers\Admin\UserActivityLogController;
use App\Http\Controllers\Admin\UserDeviceController as AdminUserDeviceController;
use App\Http\Controllers\Admin\UserOnlinePingController;
use App\Http\Controllers\Admin\UserWalletController;
use App\Http\Controllers\Admin\WithdrawalController;
use App\Http\Controllers\ApiIntegrationController;
use App\Http\Controllers\ApkController;
use App\Http\Controllers\AppHomeController;
use App\Http\Controllers\DisputeController;
use App\Http\Controllers\IncomingSmsLogController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\MainPageController;
use App\Http\Controllers\Merchant\DashboardStatsController as MerchantDashboardStatsController;
use App\Http\Controllers\Merchant\MerchantApiLogController as MerchantMerchantApiLogController;
use App\Http\Controllers\Merchant\PayoutCallbackController;
use App\Http\Controllers\Merchant\ResendCallbackController;
use App\Http\Controllers\MerchantController;
use App\Http\Controllers\ModalController;
use App\Http\Controllers\NewsController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OnlinePingController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OrderSmsLogController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PaymentDetailArchiveController;
use App\Http\Controllers\PaymentDetailController;
use App\Http\Controllers\PaymentDetailLimitResetController;
use App\Http\Controllers\PaymentDetailScheduleController;
use App\Http\Controllers\PaymentDetailVolumeStatisticsController;
use App\Http\Controllers\PayoutReceiptController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SmsLogController;
use App\Http\Controllers\SmsLogOrderController;
use App\Http\Controllers\SmsLogRejectController;
use App\Http\Controllers\Support\DepositController;
use App\Http\Controllers\Support\FilterController;
use App\Http\Controllers\Support\UserController;
use App\Http\Controllers\TeamLeader\DepositInvoiceController as TeamLeaderDepositInvoiceController;
use App\Http\Controllers\TeamLeader\TraderController;
use App\Http\Controllers\TeamLeader\TraderDisputeController;
use App\Http\Controllers\TeamLeader\TraderFinanceController;
use App\Http\Controllers\TeamLeader\TraderOrderController;
use App\Http\Controllers\TeamLeader\TraderPaymentDetailController;
use App\Http\Controllers\TelegramChatAutomationWebhookController;
use App\Http\Controllers\TelegramSettingsController;
use App\Http\Controllers\TelegramWebhookController;
use App\Http\Controllers\Trader\DepositInvoiceController;
use App\Http\Controllers\Trader\ExportController;
use App\Http\Controllers\Trader\NotificationController as TraderNotificationController;
use App\Http\Controllers\Trader\PayoutController;
use App\Http\Controllers\UserDeviceController;
use App\Http\Controllers\UserDevicePingController;
use App\Http\Controllers\UserOnlineController;
use App\Http\Controllers\Wallet\TraderBalanceTransferController;
use App\Http\Controllers\WalletController;
use App\Http\Controllers\WithdrawalAddressController;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::post('/telegram/webhook', TelegramWebhookController::class)
    ->middleware('telegram.secret')
    ->name('telegram.webhook');

Route::post('/telegram/chat-automation/webhook', TelegramChatAutomationWebhookController::class)
    ->middleware('telegram.chat-automation.secret')
    ->name('telegram.chat-automation.webhook');

// Выход из режима Impersonate
Route::post('/impersonate/leave', function () {
    $currentUser = request()->user();

    if ($currentUser?->isImpersonated()) {
        $currentUser->leaveImpersonation();

        return redirect()->route('admin.users.index');
    }

    return redirect()->back()->with('error', 'Вы не в режиме Impersonate');
})->middleware('auth', 'banned')->name('impersonate.leave');

Route::group(['middleware' => ['2fa']], function () {
    Route::get('/', AppHomeController::class)->name('dashboard');

    Route::group(['middleware' => ['auth', 'banned']], function () {
        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::patch('/profile/auth2fa', [ProfileController::class, 'updateAuth2fa'])->name('profile.update.auth2fa');
        Route::post('/profile/logout-other-devices', [ProfileController::class, 'logoutOtherDevices'])->name('profile.logout-other-devices');
        Route::patch('/profile/login-history-logging', [ProfileController::class, 'toggleLoginHistoryLogging'])->name('profile.toggle-login-history-logging');
        Route::post('/profile/avatar/regenerate', [ProfileController::class, 'regenerateAvatar'])
            ->middleware('throttle:6,2')
            ->name('profile.avatar.regenerate');
        Route::patch('/wallet/fiat-currency', [WalletController::class, 'updateFiatCurrency'])->name('wallet.fiat-currency.update');
        Route::post('/wallet/withdrawal-addresses', [WithdrawalAddressController::class, 'store'])->name('wallet.withdrawal-addresses.store');
    });

    Route::group(['middleware' => ['auth', 'banned']], function () {
        Route::post('/invoice', [InvoiceController::class, 'store'])->name('invoice.store');
        Route::post('/online/ping', [OnlinePingController::class, 'store'])->name('online.ping');
        Route::patch('/user/online', [UserOnlineController::class, 'toggle'])->name('user.online.toggle');
        Route::get('/payouts/{payout:uuid}/receipt', [PayoutReceiptController::class, 'show'])->name('payouts.receipts.show');
        Route::get('/payouts/{payout:uuid}/receipts/{receipt}', [PayoutReceiptController::class, 'showItem'])->name('payouts.receipts.item.show');
        Route::get('/news/feed', [NewsController::class, 'feed'])->name('news.feed');
        Route::post('/news/mark-read', [NewsController::class, 'markRead'])->name('news.mark-read');
        Route::post('/news/views', [NewsController::class, 'trackViews'])->name('news.views.store');
        Route::post('/news/reactions', [NewsController::class, 'react'])->name('news.reactions.store');
    });

    Route::group(['middleware' => ['auth', 'banned', 'role:Trader|Merchant|Super Admin']], function () {
        Route::get('/notifications/ping', [NotificationController::class, 'ping'])->name('notifications.ping');
        Route::patch('/notifications/sound-settings', [NotificationController::class, 'updateSoundSettings'])->name('notifications.sound.update');
        Route::post('/notifications/telegram/link', [TelegramSettingsController::class, 'refreshLink'])->name('notifications.telegram.link');
        Route::post('/notifications/telegram/unlink', [TelegramSettingsController::class, 'unlink'])->name('notifications.telegram.unlink');
    });

    Route::group(['prefix' => 'leader', 'as' => 'leader.',  'middleware' => ['auth', 'banned', 'role:Team Leader|Super Admin']], function () {
        Route::get('/main', [MainPageController::class, 'leader'])->name('main.index');
        Route::get('/finances', [WalletController::class, 'index'])->name('finances.index');
        Route::post('/deposit/invoices', [TeamLeaderDepositInvoiceController::class, 'store'])->name('deposit.invoices.store');
        Route::get('/traders', [TraderController::class, 'index'])->name('traders.index');
        Route::patch('/traders/{trader}/toggle-online', [TraderController::class, 'toggleOnline'])->name('traders.toggle-online');
        Route::patch('/traders/{trader}/commission', [TraderController::class, 'updateCommission'])->name('traders.update-commission');
        Route::get('/traders/{trader}', [TraderController::class, 'show'])->name('traders.show');
        Route::get('/traders/{trader}/payment-details', [TraderPaymentDetailController::class, 'index'])->name('traders.payment-details.index');
        Route::get('/traders/{trader}/orders', [TraderOrderController::class, 'index'])->name('traders.orders.index');
        Route::get('/traders/{trader}/disputes', [TraderDisputeController::class, 'index'])->name('traders.disputes.index');
        Route::get('/traders/{trader}/finances', [TraderFinanceController::class, 'index'])->name('traders.finances.index');
    });

    Route::group(['middleware' => ['auth', 'banned', 'role:Trader|Support|Super Admin']], function () {
        Route::resource('/orders', OrderController::class)->only(['show']);
        Route::get('/orders/{order}/unlinked-sms-logs', [OrderSmsLogController::class, 'index'])->name('orders.unlinked-sms-logs.index');
        Route::post('/orders/{order}/link-sms-log', [OrderSmsLogController::class, 'store'])->name('orders.link-sms-log.store');
        Route::get('/sms-logs/{smsLog}/unlinked-orders', [SmsLogOrderController::class, 'index'])->name('sms-logs.unlinked-orders.index');
        Route::post('/sms-logs/{smsLog}/link-order', [SmsLogOrderController::class, 'store'])->name('sms-logs.link-order.store');
        Route::post('/sms-logs/{smsLog}/reject', [SmsLogRejectController::class, 'store'])->name('sms-logs.reject.store');
        Route::get('/disputes/{dispute:uuid}/receipt', [DisputeController::class, 'receipt'])->name('disputes.receipt');
        Route::get('/disputes/{dispute:uuid}/bank-statement', [DisputeController::class, 'bankStatement'])->name('disputes.bank-statement');
    });

    Route::group(['middleware' => ['auth', 'banned', 'role:Trader|Super Admin']], function () {
        Route::get('/trader/main', [MainPageController::class, 'trader'])->name('trader.main.index');
        Route::get('/trader/main/filter-options/{type}', [MainPageController::class, 'traderFilterOptions'])->name('trader.main.filter-options');

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

        Route::post('/payment-details/{paymentDetail:uuid}/reset-limits', [PaymentDetailLimitResetController::class, 'store'])->name('payment-details.reset-limits');
        Route::post('/payment-details/{paymentDetail:uuid}/archive', [PaymentDetailArchiveController::class, 'store'])->name('payment-details.archive');
        Route::delete('/payment-details/{paymentDetail:uuid}/unarchive', [PaymentDetailArchiveController::class, 'destroy'])->name('payment-details.unarchive');
        Route::patch('/payment-details/{paymentDetail:uuid}/toggle-active', [PaymentDetailController::class, 'toggleActive'])->name('payment-details.unarchive');
        Route::patch('/payment-details/{paymentDetail:uuid}/toggle-active', [PaymentDetailController::class, 'toggleActive'])->name('payment-details.toggle-active');
        Route::get('/payment-details/{paymentDetail:uuid}/volume-statistics', [PaymentDetailVolumeStatisticsController::class, 'show'])->name('payment-details.volume-statistics');
        Route::patch('/payment-details/bulk-update', [PaymentDetailController::class, 'bulkUpdate'])->name('payment-details.bulk-update');
        Route::resource('/payment-details', PaymentDetailController::class)
            ->only(['index', 'store', 'update'])
            ->parameters(['payment-details' => 'paymentDetail:uuid']);
        Route::get('/payment-details/create-data', [PaymentDetailController::class, 'createData'])->name('payment-details.create-data');
        Route::get('/payment-details/{paymentDetail:uuid}', [PaymentDetailController::class, 'show'])->name('payment-details.show');
        Route::get('/payment-detail-schedules', [PaymentDetailScheduleController::class, 'index'])->name('payment-detail-schedules.index');
        Route::post('/payment-detail-schedules', [PaymentDetailScheduleController::class, 'store'])->name('payment-detail-schedules.store');
        Route::patch('/payment-detail-schedules/{paymentDetailSchedule}', [PaymentDetailScheduleController::class, 'update'])->name('payment-detail-schedules.update');
        Route::post('/payment-detail-schedules/{paymentDetailSchedule}/copy', [PaymentDetailScheduleController::class, 'copy'])->name('payment-detail-schedules.copy');

        // orders
        Route::resource('/orders', OrderController::class)->only(['index']);
        Route::patch('/orders/{order}/accept', [OrderController::class, 'acceptOrder'])->name('orders.accept');
        Route::patch('/orders/{order}/amount', [App\Http\Controllers\Admin\OrderController::class, 'updateAmount'])->name('orders.update.amount');

        // disputes
        Route::get('/disputes', [DisputeController::class, 'index'])->name('disputes.index');
        Route::patch('/disputes/{dispute:uuid}/accept', [DisputeController::class, 'accept'])->name('disputes.accept');
        Route::patch('/disputes/{dispute:uuid}/cancel', [DisputeController::class, 'cancel'])->name('disputes.cancel');
        Route::patch('/disputes/{dispute:uuid}/rollback', [DisputeController::class, 'rollback'])->name('disputes.rollback');

        // app
        Route::get('/bridge.apk', [ApkController::class, 'download'])->name('app.download');

        Route::get('/finances', [WalletController::class, 'index'])->name('wallet.index');

        Route::prefix('wallet/trader-transfer')->name('wallet.trader-transfer.')->group(function () {
            Route::get('/recipient', [TraderBalanceTransferController::class, 'recipient'])->name('recipient');
            Route::post('/', [TraderBalanceTransferController::class, 'store'])->name('store');
        });

        Route::get('/sms-logs', [SmsLogController::class, 'index'])->name('sms-logs.index');
        Route::get('/incoming-sms-logs', [IncomingSmsLogController::class, 'index'])->name('incoming-sms-logs.index');

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
        Route::patch('/users/{user}/toggle-traffic', [UserController::class, 'toggleTraffic'])->name('users.toggle-traffic');
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
        Route::post('/disputes/{order}', [App\Http\Controllers\Support\DisputeController::class, 'store'])->name('disputes.store');
        Route::patch('/disputes/{dispute:uuid}/accept', [App\Http\Controllers\Support\DisputeController::class, 'accept'])->name('disputes.accept');
        Route::patch('/disputes/{dispute:uuid}/cancel', [App\Http\Controllers\Support\DisputeController::class, 'cancel'])->name('disputes.cancel');
        Route::patch('/disputes/{dispute:uuid}/rollback', [App\Http\Controllers\Support\DisputeController::class, 'rollback'])->name('disputes.rollback');
        Route::get('/payouts', [App\Http\Controllers\Support\PayoutController::class, 'index'])->name('payouts.index');

        // Маршруты для фильтрации
        Route::get('/filters/detail-types', [FilterController::class, 'getDetailTypes']);
        Route::get('/filters/payment-gateways', [FilterController::class, 'searchPaymentGateways']);
        Route::get('/filters/users', [FilterController::class, 'searchUsers']);
    });

    // common
    Route::group(['middleware' => ['auth', 'banned', 'role:Trader|Super Admin']], function () {
        Route::get('/modal/sms-logs/{user}', [ModalController::class, 'smsLogs'])->name('modal.user.sms-logs');
    });

    Route::group(['middleware' => ['auth', 'banned', 'role:Merchant|Super Admin']], function () {
        Route::get('/merchant/main', [MainPageController::class, 'merchant'])->name('merchant.main.index');
        Route::get('/merchant/main/filter-options/{type}', [MainPageController::class, 'merchantFilterOptions'])->name('merchant.main.filter-options');
        Route::get('/merchant/main/api-log-stats', [MerchantDashboardStatsController::class, 'merchantApi'])->name('merchant.main.api-log-stats');

        Route::resource('/merchants', MerchantController::class)->only(['index', 'store']);
        Route::get('/merchants/data', [MerchantController::class, 'indexData'])->name('merchants.data');
        Route::get('/merchants/{merchant}/settings', [MerchantController::class, 'settings'])->name('merchants.settings');
        Route::patch('/merchants/{merchant}/callback', [MerchantController::class, 'updateCallbackURL'])->name('merchants.callback.update');
        Route::patch('/merchants/{merchant}/commission-settings', [MerchantController::class, 'updateCommissionSettings'])->name('merchants.commission-settings.update');

        Route::get('/merchant/finances', [WalletController::class, 'index'])->name('merchant.finances.index');

        Route::get('/merchant/payouts', [App\Http\Controllers\Merchant\PayoutController::class, 'index'])->name('merchant.payouts.index');
        Route::post('/merchant/payouts/{payout:uuid}/confirm-paid', [App\Http\Controllers\Merchant\PayoutController::class, 'confirmPaid'])->name('merchant.payouts.confirm-paid');
        Route::post('/merchant/payouts/{payout:uuid}/callback/resend', [PayoutCallbackController::class, 'resend'])->name('merchant.payouts.callback.resend');

        Route::resource('/payments', PaymentController::class)->only(['index']);
        Route::get('/merchant-api-logs', [MerchantMerchantApiLogController::class, 'index'])->name('merchant.merchant-api-logs.index');
        Route::get('/merchant-api-logs/amount-distribution', [MerchantMerchantApiLogController::class, 'amountDistribution'])->name('merchant.merchant-api-logs.amount-distribution');

        Route::post('/payment/{order}/callback/resend', [ResendCallbackController::class, 'resend'])->name('payment.callback.resend');
    });

    Route::group(['middleware' => ['auth', 'banned', 'role:Merchant|Super Admin']], function () {
        Route::get('/integration', [ApiIntegrationController::class, 'index'])->name('integration.index');
        Route::post('/integration/regenerate-token', [ApiIntegrationController::class, 'regenerateToken'])
            ->name('integration.regenerate-token');
        Route::post('/integration/regenerate-webhook-secret', [ApiIntegrationController::class, 'regenerateWebhookSecret'])
            ->name('integration.regenerate-webhook-secret');
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
        Route::post('/news', [NewsController::class, 'store'])->name('news.store');
        Route::post('/news/format', [NewsController::class, 'format'])->name('news.format');
        Route::delete('/news/{newsPost}', [NewsController::class, 'destroy'])->name('news.destroy');
        Route::get('/main/filter-options/{type}', [MainPageController::class, 'adminFilterOptions'])->name('main.filter-options');
        Route::get('/main/anti-fraud-stats', [DashboardStatsController::class, 'antiFraud'])->name('main.anti-fraud-stats');
        Route::get('/main/api-log-stats', [DashboardStatsController::class, 'merchantApi'])->name('main.api-log-stats');
        Route::get('/main/enabled-cards-stats', [DashboardStatsController::class, 'enabledCards'])->name('main.enabled-cards-stats');
        Route::get('/notifications', [AdminNotificationController::class, 'index'])->name('notifications.index');

        Route::get('/app', [App\Http\Controllers\Admin\ApkController::class, 'index'])->name('app.index');
        Route::post('/app', [App\Http\Controllers\Admin\ApkController::class, 'store'])->name('app.store');
        Route::get('/integration-api', [IntegrationApiController::class, 'index'])->name('integration-api.index');
        Route::post('/integration-api/regenerate-token', [IntegrationApiController::class, 'regenerateToken'])->name('integration-api.regenerate-token');
        Route::patch('/open-ai', [OpenAiSettingController::class, 'update'])->name('open-ai.update');
        Route::post('/open-ai/models', [OpenAiSettingController::class, 'refreshModels'])->name('open-ai.models.refresh');
        Route::get('/telegram-bot/settings', [TelegramBotSettingController::class, 'show'])->name('telegram-bot.settings.show');
        Route::patch('/telegram-bot/settings', [TelegramBotSettingController::class, 'update'])->name('telegram-bot.settings.update');
        Route::post('/telegram-bot/webhook', [TelegramBotSettingController::class, 'setupWebhook'])->name('telegram-bot.webhook.setup');
        Route::get('/telegram-chats', [TelegramChatController::class, 'index'])->name('telegram-chats.index');
        Route::get('/telegram-chats/trader-search', [TelegramChatTraderController::class, 'search'])->name('telegram-chats.trader-search');
        Route::post('/telegram-chats/{telegramChat}/traders', [TelegramChatTraderController::class, 'store'])->name('telegram-chats.traders.store');
        Route::patch('/telegram-chats/{telegramChat}/traders/{trader}', [TelegramChatTraderController::class, 'update'])->name('telegram-chats.traders.update');
        Route::delete('/telegram-chats/{telegramChat}/traders/{trader}', [TelegramChatTraderController::class, 'destroy'])->name('telegram-chats.traders.destroy');
        Route::get('/telegram-chats/{telegramChat}/messages', [TelegramChatController::class, 'messages'])->name('telegram-chats.messages.index');
        Route::patch('/telegram-chats/{telegramChat}', [TelegramChatController::class, 'update'])->name('telegram-chats.update');
        Route::post('/telegram-chats/{telegramChat}/archive', [TelegramChatController::class, 'archive'])->name('telegram-chats.archive');
        Route::post('/telegram-chats/{telegramChat}/restore', [TelegramChatController::class, 'restore'])->name('telegram-chats.restore');
        Route::patch('/telegram-chats/{telegramChat}/debug', [TelegramChatController::class, 'toggleDebug'])->name('telegram-chats.debug.update');
        Route::get('/telegram-chats/{telegramChat}/messages/{telegramChatMessage}/attachments/{attachment}', [TelegramChatAttachmentController::class, 'show'])
            ->name('telegram-chats.messages.attachments.show');
        Route::get('/anti-fraud/settings', [AntiFraudSettingController::class, 'index'])->name('anti-fraud.settings.index');
        Route::post('/anti-fraud/settings', [AntiFraudSettingController::class, 'store'])->name('anti-fraud.settings.store');
        Route::patch('/anti-fraud/settings/{anti_fraud_setting}', [AntiFraudSettingController::class, 'update'])->name('anti-fraud.settings.update');
        Route::delete('/anti-fraud/settings/{anti_fraud_setting}', [AntiFraudSettingController::class, 'destroy'])->name('anti-fraud.settings.destroy');
        Route::get('/anti-fraud/history', [AntiFraudHistoryController::class, 'index'])->name('anti-fraud.history.index');
        Route::get('/anti-fraud/clients', [AntiFraudClientController::class, 'index'])->name('anti-fraud.clients.index');
        Route::get('/anti-fraud/clients/{merchantClient}/orders', [AntiFraudClientController::class, 'orders'])->name('anti-fraud.clients.orders');
        Route::get('/profit-calculator', [ProfitCalculatorController::class, 'index'])->name('profit-calculator.index');
        Route::post('/profit-calculator/calculate', [ProfitCalculatorController::class, 'calculate'])->name('profit-calculator.calculate');

        Route::post('/enabled-cards/limit-levels', [EnabledCardsController::class, 'storeLimitLevel'])->name('enabled-cards.limit-levels.store');
        Route::delete('/enabled-cards/limit-levels', [EnabledCardsController::class, 'destroyLimitLevel'])->name('enabled-cards.limit-levels.destroy');

        // Маршруты для фильтрации
        Route::get('/filters/detail-types', [App\Http\Controllers\Admin\FilterController::class, 'getDetailTypes']);
        Route::get('/filters/payment-gateways', [App\Http\Controllers\Admin\FilterController::class, 'searchPaymentGateways']);
        Route::get('/filters/users', [App\Http\Controllers\Admin\FilterController::class, 'searchUsers']);

        Route::patch('/users/{user}/toggle-online', [App\Http\Controllers\Admin\UserController::class, 'toggleOnline'])->name('users.toggle-online');
        Route::post('/users/{user}/archive', [App\Http\Controllers\Admin\UserController::class, 'archive'])->name('users.archive');
        Route::delete('/users/{user}/unarchive', [App\Http\Controllers\Admin\UserController::class, 'unarchive'])->name('users.unarchive');
        Route::get('/users/roles', [App\Http\Controllers\Admin\UserController::class, 'roles'])->name('users.roles');
        Route::get('/users/team-leaders', [App\Http\Controllers\Admin\UserController::class, 'teamLeaders'])->name('users.team-leaders');
        Route::get('/users/{user}/online-pings', [UserOnlinePingController::class, 'index'])->name('users.online-pings');
        Route::get('/users/{user}', [App\Http\Controllers\Admin\UserController::class, 'show'])->name('users.show');
        Route::resource('/users', App\Http\Controllers\Admin\UserController::class)->only(['index', 'store', 'update']);
        Route::delete('/users/{user}/reset-2fa', [App\Http\Controllers\Admin\UserController::class, 'reset2fa'])->name('users.reset-2fa');
        Route::get('/payment-gateways', [PaymentGatewayController::class, 'index'])->name('payment-gateways.index');
        Route::get('/payment-gateways/create-data', [PaymentGatewayController::class, 'createData'])->name('payment-gateways.create-data');
        Route::get('/payment-gateways/bulk-settings-data', [PaymentGatewayController::class, 'bulkSettingsData'])->name('payment-gateways.bulk-settings-data');
        Route::post('/payment-gateways', [PaymentGatewayController::class, 'store'])->name('payment-gateways.store');
        Route::get('/payment-gateways/{paymentGateway}/edit-data', [PaymentGatewayController::class, 'editData'])->name('payment-gateways.edit-data');
        Route::patch('/payment-gateways/bulk-settings', [PaymentGatewayController::class, 'bulkUpdate'])->name('payment-gateways.bulk-settings.update');
        Route::patch('/payment-gateways/{paymentGateway}', [PaymentGatewayController::class, 'update'])->name('payment-gateways.update');
        Route::get('/orders', [App\Http\Controllers\Admin\OrderController::class, 'index'])->name('orders.index');
        Route::get('/payouts', [App\Http\Controllers\Admin\PayoutController::class, 'index'])->name('payouts.index');
        Route::get('/payouts/export', [App\Http\Controllers\Admin\PayoutController::class, 'export'])->name('payouts.export');
        Route::patch('/payouts/{payout}/status', [App\Http\Controllers\Admin\PayoutController::class, 'updateStatus'])->name('payouts.status.update');
        Route::patch('/payouts/{payout}/trader', [App\Http\Controllers\Admin\PayoutController::class, 'transferTrader'])->name('payouts.trader.transfer');
        Route::get('/payouts/settings-data', [App\Http\Controllers\Admin\PayoutController::class, 'settingsData'])->name('payouts.settings-data');
        Route::patch('/payouts/settings', [App\Http\Controllers\Admin\PayoutController::class, 'updateSettings'])->name('payouts.settings.update');

        Route::get('/finances', [FinancesController::class, 'index'])->name('finances.index');
        Route::get('/deposits', function () {
            return redirect()->route('admin.finances.index', array_merge(
                request()->except('tab'),
                ['tab' => 'deposits'],
            ));
        })->name('deposits.index');
        Route::get('/withdrawals', function () {
            return redirect()->route('admin.finances.index', array_merge(
                request()->except('tab'),
                ['tab' => 'withdrawals'],
            ));
        })->name('withdrawals.index');
        Route::get('/withdrawals/address/whitelist', [AddressWhitelistController::class, 'index'])->name('withdrawals.address.whitelist.index');
        Route::patch('/withdrawals/{invoice}/success', [WithdrawalController::class, 'success'])->name('withdrawals.success');
        Route::patch('/withdrawals/{invoice}/fail', [WithdrawalController::class, 'fail'])->name('withdrawals.fail');

        Route::get('/rate-sources/options', [RateSourceController::class, 'options'])->name('rate-sources.options');
        Route::get('/rate-sources/filter-options', [RateSourceController::class, 'filterOptions'])->name('rate-sources.filter-options');
        Route::post('/rate-sources/preview', [RateSourceController::class, 'preview'])->name('rate-sources.preview');
        Route::post('/rate-sources/{rateSource}/refresh', [RateSourceController::class, 'refresh'])->name('rate-sources.refresh');
        Route::get('/rate-sources', [RateSourceController::class, 'index'])->name('rate-sources.index');
        Route::post('/rate-sources', [RateSourceController::class, 'store'])->name('rate-sources.store');
        Route::patch('/rate-sources/{rateSource}', [RateSourceController::class, 'update'])->name('rate-sources.update');
        Route::delete('/rate-sources/{rateSource}', [RateSourceController::class, 'destroy'])->name('rate-sources.destroy');

        Route::get('/sms-logs', [App\Http\Controllers\Admin\SmsLogController::class, 'index'])->name('sms-logs.index');
        Route::get('/incoming-sms-logs', [IncomingSmsLogController::class, 'index'])->name('incoming-sms-logs.index');
        Route::get('/devices', [AdminUserDeviceController::class, 'index'])->name('devices.index');
        Route::get('/devices/{device}/connect-snapshot', [AdminUserDeviceController::class, 'connectSnapshot'])
            ->name('devices.connect-snapshot.show');
        Route::post('/sender-stop-list/{smsLog}', [SenderStopListController::class, 'store'])->name('sender-stop-list.store');
        Route::post('/sender-payment-gateway/{smsLog}', [SenderStopListController::class, 'attachToPaymentGateway'])->name('sender-payment-gateway.store');
        Route::delete('/sender-stop-list/{senderStopList}', [SenderStopListController::class, 'destroy'])->name('sender-stop-list.destroy');
        Route::post('/sms-stop-word', [SmsStopWordController::class, 'store'])->name('sms-stop-word.store');
        Route::delete('/sms-stop-word/{smsStopWord}', [SmsStopWordController::class, 'destroy'])->name('sms-stop-word.destroy');

        Route::get('/payment-details', [App\Http\Controllers\Admin\PaymentDetailController::class, 'index'])->name('payment-details.index');
        Route::get('/payment-details/{paymentDetail:uuid}/volume-statistics', [PaymentDetailVolumeStatisticsController::class, 'show'])->name('payment-details.volume-statistics');

        Route::get('/disputes', [App\Http\Controllers\Admin\DisputeController::class, 'index'])->name('disputes.index');
        Route::post('/disputes/{order}', [App\Http\Controllers\Admin\DisputeController::class, 'store'])->name('disputes.store');

        Route::get('/users/{user}/wallet', [UserWalletController::class, 'index'])->name('users.wallet.index');
        Route::get('/users/{user}/wallet/transactions/export', [UserWalletController::class, 'exportTransactions'])->name('users.wallet.transactions.export');
        Route::post('/users/{user}/wallet/deposit', [UserWalletController::class, 'deposit'])->name('users.wallet.deposit');
        Route::post('/users/{user}/wallet/withdraw', [UserWalletController::class, 'withdraw'])->name('users.wallet.withdraw');

        Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
        Route::patch('/settings/update/app-slogan', [SettingsController::class, 'updateAppSlogan'])->name('settings.update.app-slogan');
        Route::patch('/settings/update/prime-time-bonus', [SettingsController::class, 'updatePrimeTimeBonus'])->name('settings.update.prime-time-bonus');
        Route::patch('/settings/update/funds-on-hold', [SettingsController::class, 'updateFundsOnHold'])->name('settings.update.funds-on-hold');
        Route::patch('/settings/update/max-pending-disputes', [SettingsController::class, 'updateMaxPendingDisputes'])->name('settings.update.max-pending-disputes');
        Route::patch('/settings/update/max-rejected-disputes', [SettingsController::class, 'updateMaxRejectedDisputes'])->name('settings.update.max-rejected-disputes');
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
        Route::get('/activity-logs', [UserActivityLogController::class, 'index'])->name('activity-logs.index');
        Route::get('/merchant-api-logs/amount-distribution', [MerchantApiLogController::class, 'amountDistribution'])->name('merchant-api-logs.amount-distribution');
        Route::post('/merchant-api-logs/delete', [MerchantApiLogController::class, 'deleteByDateRange'])->name('merchant-api-logs.delete');
        Route::redirect('/callback-logs', '/admin/merchant-api-logs?tab=callbacks')->name('callback-logs.index');

        // Только для локальной разработки: страница со всеми компонентами
        if (is_local()) {
            Route::get('/dev/components', function () {
                return Inertia::render('Dev/ComponentsGallery');
            })->name('dev.components');
        }
    });

});

require __DIR__.'/auth.php';
