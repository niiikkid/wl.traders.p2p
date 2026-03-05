<?php

namespace App\Providers;

use App\Contracts\DeviceServiceContract;
use App\Contracts\DisputeServiceContract;
use App\Contracts\FundsHolderServiceContract;
use App\Contracts\InvoiceServiceContract;
use App\Contracts\LoginHistoryServiceContract;
use App\Contracts\MainPageCacheServiceContract;
use App\Contracts\MainPageStatsServiceContract;
use App\Contracts\MarketServiceContract;
use App\Contracts\CallbackServiceContract;
use App\Contracts\MerchantApiLogServiceContract;
use App\Contracts\MerchantApiStatisticsServiceContract;
use App\Contracts\OrderPoolingServiceContract;
use App\Contracts\OrderServiceContract;
use App\Contracts\PayoutServiceContract;
use App\Contracts\ProfitServiceContract;
use App\Contracts\AntiFraudSettingServiceContract;
use App\Contracts\AntiFraudServiceContract;
use App\Contracts\NotificationServiceContract;
use App\Contracts\TelegramServiceContract;
use App\Contracts\QueriesBuilderContract;
use App\Contracts\ServiceBuilderContract;
use App\Contracts\SettingsServiceContract;
use App\Contracts\SmsServiceContract;
use App\Contracts\WalletServiceContract;
use App\Contracts\UserServiceContract;
use App\Contracts\PaymentDetailServiceContract;
use App\Contracts\MerchantServiceContract;
use App\Events\OrderSucceeded;
use App\Listeners\UpdateTempVipProgressListener;
use App\Mixins\ResponseMixins;
use App\Models\Dispute;
use App\Models\Merchant;
use App\Models\Order;
use App\Models\PaymentDetail;
use App\Models\Payout\Payout as PayoutModel;
use App\Models\User;
use App\Queries\Cache\MerchantQueriesCache;
use App\Queries\Eloquent\DisputeQueriesEloquent;
use App\Queries\Eloquent\InvoiceQueriesEloquent;
use App\Queries\Eloquent\MerchantQueriesEloquent;
use App\Queries\Eloquent\OrderQueriesEloquent;
use App\Queries\Eloquent\PaymentDetailQueriesEloquent;
use App\Queries\Eloquent\PaymentGatewayQueriesEloquent;
use App\Queries\Eloquent\TransactionQueriesEloquent;
use App\Queries\Eloquent\PayoutQueriesEloquent;
use App\Queries\Eloquent\MerchantApiLogQueriesEloquent;
use App\Queries\Eloquent\CallbackLogQueriesEloquent;
use App\Queries\Interfaces\DisputeQueries;
use App\Queries\Interfaces\InvoiceQueries;
use App\Queries\Interfaces\MerchantQueries;
use App\Queries\Interfaces\OrderQueries;
use App\Queries\Interfaces\PayoutQueries;
use App\Queries\Interfaces\PaymentDetailQueries;
use App\Queries\Interfaces\PaymentGatewayQueries;
use App\Queries\Interfaces\TransactionQueries;
use App\Queries\Interfaces\MerchantApiLogQueries;
use App\Queries\Interfaces\CallbackLogQueries;
use App\Queries\QueriesBuilder;
use App\Services\Auth\LoginHistoryService;
use App\Services\Device\DeviceService;
use App\Services\Dispute\DisputeService;
use App\Services\Invoice\InvoiceService;
use App\Services\MainPage\MainPageCacheService;
use App\Services\MainPage\MainPageStatsService;
use App\Services\Market\MarketService;
use App\Services\MoneyHolder\FundsHolderService;
use App\Services\Order\OrderService;
use App\Services\OrderCallback\CallbackService;
use App\Services\OrderPooling\OrderPoolingService;
use App\Services\Payout\PayoutService;
use App\Services\Profit\ProfitService;
use App\Services\AntiFraud\AntiFraudSettingService;
use App\Services\AntiFraud\AntiFraudService;
use App\Services\Notification\NotificationService;
use App\Services\ServiceBuilder;
use App\Services\Settings\SettingsService;
use App\Services\Sms\SmsService;
use App\Services\Statistics\MerchantApiStatisticsService;
use App\Services\Telegram\TelegramService;
use App\Services\Wallet\WalletService;
use App\Services\User\UserService;
use App\Services\PaymentDetail\PaymentDetailService;
use App\Services\Logging\MerchantApiLogService;
use App\Services\Merchant\MerchantService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Queue\Events\JobFailed;
use Carbon\Carbon;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //services
        $this->app->singleton(ServiceBuilderContract::class, function () {
            return new ServiceBuilder();
        });
        $this->app->bind(OrderServiceContract::class, function () {
            return new OrderService();
        });
        $this->app->bind(SmsServiceContract::class, function () {
            return new SmsService();
        });
        $this->app->bind(CallbackServiceContract::class, function () {
            return new CallbackService();
        });
        $this->app->singleton(MarketServiceContract::class, function () {
            return new MarketService();
        });
        $this->app->singleton(DisputeServiceContract::class, function () {
            return new DisputeService();
        });
        $this->app->singleton(WalletServiceContract::class, function () {
            return new WalletService();
        });
        $this->app->singleton(InvoiceServiceContract::class, function () {
            return new InvoiceService();
        });
        $this->app->singleton(SettingsServiceContract::class, function () {
            return new SettingsService();
        });
        $this->app->singleton(FundsHolderServiceContract::class, function () {
            return new FundsHolderService();
        });
        $this->app->bind(LoginHistoryServiceContract::class, function () {
            return new LoginHistoryService();
        });
        $this->app->singleton(MerchantApiLogServiceContract::class, function () {
            return new MerchantApiLogService();
        });
        $this->app->singleton(OrderPoolingServiceContract::class, function () {
            return new OrderPoolingService();
        });
        $this->app->singleton(UserServiceContract::class, function () {
            return new UserService();
        });
        $this->app->singleton(PaymentDetailServiceContract::class, function () {
            return new PaymentDetailService();
        });
        $this->app->singleton(DeviceServiceContract::class, function () {
            return new DeviceService();
        });
        $this->app->singleton(MerchantApiStatisticsServiceContract::class, function () {
            return new MerchantApiStatisticsService();
        });
        $this->app->singleton(MerchantServiceContract::class, function () {
            return new MerchantService();
        });
        $this->app->singleton(PayoutServiceContract::class, function () {
            return new PayoutService();
        });
        $this->app->singleton(ProfitServiceContract::class, function () {
            return new ProfitService();
        });
        $this->app->singleton(AntiFraudSettingServiceContract::class, function () {
            return new AntiFraudSettingService();
        });
        $this->app->singleton(AntiFraudServiceContract::class, function () {
            return new AntiFraudService();
        });
        $this->app->singleton(NotificationServiceContract::class, function () {
            return new NotificationService(
                templateResolver: new \App\Services\Notification\Templates\NotificationTemplateResolver()
            );
        });
        $this->app->singleton(TelegramServiceContract::class, function () {
            return new TelegramService();
        });
        $this->app->singleton(MainPageStatsServiceContract::class, function () {
            return new MainPageStatsService();
        });
        $this->app->singleton(MainPageCacheServiceContract::class, function () {
            return new MainPageCacheService(
                statsService: make(MainPageStatsServiceContract::class),
            );
        });

        // Регистрация LoginLogger
        $this->app->singleton('login-logger', function () {
            return new \App\Support\LoginLogger();
        });

        //queries
        $this->app->singleton(QueriesBuilderContract::class, function () {
            return new QueriesBuilder();
        });
        $this->app->bind(OrderQueries::class, function () {
            return new OrderQueriesEloquent();
        });
        $this->app->bind(PaymentGatewayQueries::class, function () {
            return new PaymentGatewayQueriesEloquent();
        });
        $this->app->bind(PaymentDetailQueries::class, function () {
            return new PaymentDetailQueriesEloquent();
        });
        $this->app->bind(DisputeQueries::class, function () {
            return new DisputeQueriesEloquent();
        });
        $this->app->bind(MerchantQueries::class, function () {
            return new MerchantQueriesCache(
                eloquentQueries: new MerchantQueriesEloquent(),
                cacheTtl: 60
            );
        });
        $this->app->bind(InvoiceQueries::class, function () {
            return new InvoiceQueriesEloquent();
        });
        $this->app->bind(TransactionQueries::class, function () {
            return new TransactionQueriesEloquent();
        });
        $this->app->bind(MerchantApiLogQueries::class, function () {
            return new MerchantApiLogQueriesEloquent();
        });
        $this->app->bind(CallbackLogQueries::class, function () {
            return new CallbackLogQueriesEloquent();
        });
        $this->app->bind(PayoutQueries::class, function () {
            return new PayoutQueriesEloquent();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Queue::failing(function (JobFailed $event) {
            if ($event->job->getQueue() === 'conversion-prices-parser') {
                // Удаляем задачу, чтобы она не сохранялась в failed_jobs
                $event->job->delete();
            }
        });

        Gate::define('viewPulse', function (User $user) {
            return $user->hasRole('Super Admin');
        });

        Response::mixin(new ResponseMixins());

        Event::listen(OrderSucceeded::class, UpdateTempVipProgressListener::class);

        Gate::define('access-to-payment-detail', function (User $user, PaymentDetail $paymentDetail) {
            return $user->id === $paymentDetail->user_id || $user->hasRole('Super Admin');
        });
        Gate::define('access-to-order', function (User $user, Order $order) {
            return $user->id === $order->paymentDetail?->user_id || $user->id === $order->merchant->user_id || $user->hasRole('Super Admin');
        });
        Gate::define('access-to-order-for-merchant-support', function (User $user, Order $order) {
            return $user->merchant?->id === $order->merchant->user_id || $user->id === $order->merchant->user_id || $user->hasRole('Super Admin');
        });
        Gate::define('access-to-merchant', function (User $user, Merchant $merchant) {
            return $user->id === $merchant->user_id || $user->hasRole('Super Admin');
        });
        Gate::define('access-to-dispute', function (User $user, Dispute $dispute) {
            return $user->id === optional($dispute->order->paymentDetail)->user_id || $user->hasRole('Super Admin');
        });
        Gate::define('access-to-dispute-receipt', function (User $user, Dispute $dispute) {
            return $user->id === optional($dispute->order->paymentDetail)->user_id || $user->hasRole('Super Admin') || $user->hasRole('Support');
        });
        Gate::define('access-to-self', function (User $user) {
            return $user->id === auth()->id() || $user->hasRole('Super Admin');
        });
        //api
        Gate::define('api-access-to-merchant', function (User $user, Merchant $merchant) {
            return $user->id === $merchant->user_id;
        });

        Route::bind('order', function($id, \Illuminate\Routing\Route $route) {
            if ($route->bindingFieldFor('order') === 'uuid') {
                return Order::withoutGlobalScopes()->where('uuid', $id)->firstOrFail();
            }

            return Order::findOrFail($id);
        });

        Route::bind('payout', function ($id, \Illuminate\Routing\Route $route) {
            if ($route->bindingFieldFor('payout') === 'uuid') {
                return PayoutModel::query()->where('uuid', $id)->firstOrFail();
            }

            return PayoutModel::query()->findOrFail($id);
        });
    }
}
