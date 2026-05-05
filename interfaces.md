# interface CascadeProviderInterface

## public function createDeal(CascadeDeal $cascadeDeal, ?int $maxWaitMs = null): array;

### должен возвращать DTO с полями:

provider_deal_id, (id сделки внутри системы провайдера),
status (статус сделки, CascadeDealStatus, мапить внутри интеграции),
amount (базовая сумма, обычно фиат),
merchant_profit (сколько провайдер платит нам, всегда USDT),
currency (валюта поля amount),
bank_name (банк платежа),
recipient_name (получатель),
payin_detail (реквизит),
created_at (время создания реквизита в toIso8601String),
data_for_logging (данные для логирования в виде array)

## public function cancelDeal(CascadeDeal $cascadeDeal, string $providerDealId): array;

### должен возвращать bool:
true - успех
false - не успех

## public function getDeal(CascadeDeal $cascadeDeal, string $providerDealId): array;

### должен возвращать DTO с полями:

provider_deal_id, (id сделки внутри системы провайдера),
status (статус сделки, CascadeDealStatus, мапить внутри интеграции),
amount (базовая сумма, обычно фиат),
merchant_profit (сколько провайдер платит нам, всегда USDT),
currency (валюта поля amount),
bank_name (банк платежа),
recipient_name (получатель),
payin_detail (реквизит),
created_at (время создания реквизита в toIso8601String),
data_for_logging (данные для логирования в виде array)

## public function storeConfirmationCode(CascadeDeal $cascadeDeal, string $confirmationCode): array;

### должен возвращать bool:
true - успех
false - не успех

## public function openDispute(CascadeDeal $cascadeDeal, string $providerDealId, array $data = []): array;

### должен возвращать bool:
true - успех
false - не успех

## public function getDispute(CascadeDeal $cascadeDeal, string $providerDealId, string $disputeId): array;





