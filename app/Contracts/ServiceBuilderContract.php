<?php

namespace App\Contracts;

interface ServiceBuilderContract
{
    public function order(): OrderServiceContract;

    public function sms(): SmsServiceContract;

    public function callback(): CallbackServiceContract;

    public function market(): MarketServiceContract;

    public function dispute(): DisputeServiceContract;

    public function wallet(): WalletServiceContract;

    public function invoice(): InvoiceServiceContract;

    public function settings(): SettingsServiceContract;

    public function fundsHolder(): FundsHolderServiceContract;

    public function loginHistory(): LoginHistoryServiceContract;

    public function merchantApiLog(): MerchantApiLogServiceContract;

    public function orderPooling(): OrderPoolingServiceContract;

    public function device(): DeviceServiceContract;

    public function merchantApiStatistics(): MerchantApiStatisticsServiceContract;

    public function user(): UserServiceContract;

    public function paymentDetail(): PaymentDetailServiceContract;

    public function merchant(): MerchantServiceContract;

    public function payout(): PayoutServiceContract;

    public function profit(): ProfitServiceContract;

    public function antiFraudSetting(): AntiFraudSettingServiceContract;

    public function antiFraud(): AntiFraudServiceContract;

    public function notification(): NotificationServiceContract;

    public function telegram(): TelegramServiceContract;
}
