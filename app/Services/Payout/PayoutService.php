<?php

namespace App\Services\Payout;

use App\Contracts\PayoutServiceContract;
use App\DTO\Payout\PayoutCreateDTO;
use App\Enums\BalanceType;
use App\Enums\MarketEnum;
use App\Enums\PayoutOperationType;
use App\Enums\PayoutStatus;
use App\Enums\TransactionType;
use App\Exceptions\PayoutException;
use App\Models\Merchant;
use App\Models\Payout\Payout;
use App\Models\PaymentGateway;
use App\Models\User;
use App\Models\Wallet;
use App\Jobs\CreditPayoutToTraderJob;
use App\Jobs\ExpiresPayoutJob;
use App\Jobs\SendPayoutCallbackJob;
use App\Services\Money\Currency;
use App\Services\Money\Money;
use App\Utils\Transaction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PayoutService implements PayoutServiceContract
{
    private const RECEIPT_DISK = 'local';
    private const RECEIPT_DIRECTORY = 'receipts/payouts';

    /**
     * @throws PayoutException
     */
    public function create(PayoutCreateDTO $data): Payout
    {
        return Transaction::run(function () use ($data) {
            if ($data->paymentGateway) {
                $this->ensureGatewaySupportsPayouts($data->paymentGateway);
            }
            $this->ensureMerchantCanCreatePayouts($data->merchant);

            $merchantWallet = $this->resolveMerchantWallet($data->merchant);
            $callbackUrl = $data->callbackUrl ?: $data->merchant->payout_callback_url;

            $currency = $data->amountFiat->getCurrency();
            $geoMarket = $this->resolveGeoMarket($data->merchant, $currency);
            $conversionPrice = services()->market()->getBuyPrice(
                $currency,
                $geoMarket,
                false
            );

            if (! $conversionPrice->greaterThanZero()) {
                throw PayoutException::marketPriceUnavailable();
            }

            $payoutSettings = $this->resolvePayoutSettings($data->paymentGateway, $currency);
            $totalRate = $payoutSettings['total_rate'];
            $traderRate = $payoutSettings['trader_rate'];
            $teamLeaderRate = 0.0;

            $profits = services()->profit()->calculateOutBody(
                sourceAmount: $data->amountFiat,
                exchangeRate: $conversionPrice,
                totalFeeRate: $totalRate,
                traderFeeRate: $traderRate,
                teamLeaderFeeRate: $teamLeaderRate,
                teamLeaderServiceSplitPercent: null
            );

            $usdtBody = $profits->convertedAmount;
            $totalFee = $profits->totalFee;
            $traderFee = $profits->traderFee;
            $teamLeadFee = $profits->teamLeaderFee;
            $serviceFee = $profits->serviceFee;
            $merchantDebit = $profits->merchantDebit;
            $traderCredit = $profits->traderCredit;
            $available = services()->wallet()->getTotalAvailableBalance($merchantWallet, BalanceType::MERCHANT);

            // Время истечения заявки (протухания) согласно настройке gateway
            $expiresAt = null;
            $reservationMinutes = $payoutSettings['reservation_minutes'];
            if ($reservationMinutes > 0) {
                $expiresAt = now()->addMinutes($reservationMinutes);
            }

            if ($available->lessThan($merchantDebit)) {
                throw PayoutException::insufficientMerchantFunds();
            }

            $rateFixedAt = Carbon::now();

            $payout = Payout::query()->create([
                'uuid' => (string) Str::uuid(),
                'external_id' => $data->externalId,
                'merchant_id' => $data->merchant->id,
                'payment_gateway_id' => $data->paymentGateway?->id,
                'bank_name' => $data->bankName,
                'payout_method_type' => $data->methodType,
                'requisites' => $data->requisites,
                'initials' => $data->initials,
                'callback_url' => $callbackUrl,
                'amount_fiat' => $data->amountFiat,
                'amount_fiat_currency' => strtoupper($data->amountFiat->getCurrency()->getCode()),
                'usdt_body' => $usdtBody,
                'usdt_body_currency' => $usdtBody->getCurrency()->getCode(),
                'total_fee' => $totalFee,
                'total_fee_currency' => $totalFee->getCurrency()->getCode(),
                'trader_fee' => $traderFee,
                'trader_fee_currency' => $traderFee->getCurrency()->getCode(),
                'teamlead_fee' => $teamLeadFee,
                'teamlead_fee_currency' => $teamLeadFee->getCurrency()->getCode(),
                'teamlead_split_from_service_percent' => null,
                'teamlead_split_from_trader_percent' => null,
                'service_fee' => $serviceFee,
                'service_fee_currency' => $serviceFee->getCurrency()->getCode(),
                'merchant_debit' => $merchantDebit,
                'merchant_debit_currency' => $merchantDebit->getCurrency()->getCode(),
                'trader_credit' => $traderCredit,
                'trader_credit_currency' => $traderCredit->getCurrency()->getCode(),
                'rate_market' => $geoMarket,
                'conversion_price' => $conversionPrice,
                'conversion_price_currency' => strtoupper($conversionPrice->getCurrency()->getCode()),
                'rate_fixed_at' => $rateFixedAt,
                'status' => PayoutStatus::OPEN,
                'expires_at' => $expiresAt,
                'total_commission_rate' => $totalRate,
                'trader_commission_rate' => $traderRate,
                'teamlead_commission_rate' => $teamLeaderRate,
            ]);

            services()->wallet()->takeFromBalance(
                walletID: $merchantWallet->id,
                amount: $merchantDebit,
                transactionType: TransactionType::PAYMENT_FOR_OPENED_PAYOUT,
                balanceType: BalanceType::MERCHANT
            );

            $this->logOperation($payout, PayoutOperationType::RESERVE_FROM_MERCHANT, $merchantDebit, [
                'wallet_id' => $merchantWallet->id,
            ]);

            if ($expiresAt) {
                ExpiresPayoutJob::dispatch($payout)->delay($expiresAt);
            }

            SendPayoutCallbackJob::dispatch($payout);

            return $payout->load('merchant', 'paymentGateway');
        });
    }

    /**
     * @throws PayoutException
     */
    public function cancel(Payout $payout): Payout
    {
        return Transaction::run(function () use ($payout) {
            $payout->refresh()->loadMissing('merchant.user.wallet');

            if ($payout->status->notEquals(PayoutStatus::OPEN)) {
                throw PayoutException::payoutNotOpen();
            }

            if ($payout->trader_id !== null) {
                throw PayoutException::payoutAlreadyTaken();
            }

            $merchantWallet = $this->resolveMerchantWallet($payout->merchant);

            services()->wallet()->giveToBalance(
                walletID: $merchantWallet->id,
                amount: $payout->merchant_debit,
                transactionType: TransactionType::REFUND_FOR_CANCELED_PAYOUT,
                balanceType: BalanceType::MERCHANT
            );

            $payout->update([
                'status' => PayoutStatus::CANCELED,
                'canceled_at' => now(),
            ]);

            $this->logOperation($payout, PayoutOperationType::RETURN_TO_MERCHANT, $payout->merchant_debit, [
                'wallet_id' => $merchantWallet->id,
            ]);

            return $payout->load('merchant', 'paymentGateway');
        });
    }

    /**
     * @throws PayoutException
     */
    public function take(Payout $payout, User $trader): Payout
    {
        return Transaction::run(function () use ($payout, $trader) {
            $payout = Payout::query()
                ->whereKey($payout->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($payout->status->notEquals(PayoutStatus::OPEN)) {
                throw PayoutException::payoutUnavailableForTaking();
            }

            $lockedTrader = User::query()
                ->whereKey($trader->id)
                ->with('teamLeader')
                ->lockForUpdate()
                ->firstOrFail();

            $limit = max((int) $lockedTrader->payout_active_payouts_limit ?: 1, 1);

            $activeCount = Payout::query()
                ->where('trader_id', $lockedTrader->id)
                ->whereIn('status', [
                    PayoutStatus::TAKEN->value,
                    PayoutStatus::SENT->value,
                ])
                ->lockForUpdate()
                ->count();

            if ($activeCount >= $limit) {
                throw PayoutException::traderActiveLimitReached($limit);
            }

            $payout->update([
                'trader_id' => $lockedTrader->id,
                'status' => PayoutStatus::TAKEN,
                'taken_at' => now(),
            ]);

            $this->applyTeamLeaderCalculation($payout, $lockedTrader);

            $this->logOperation($payout, PayoutOperationType::MARK_TAKEN, null, [
                'trader_id' => $lockedTrader->id,
            ]);

            return $payout->load('merchant', 'paymentGateway', 'trader');
        });
    }

    /**
     * @throws PayoutException
     */
    public function markSent(Payout $payout, User $trader, ?UploadedFile $receipt = null): Payout
    {
        return Transaction::run(function () use ($payout, $trader, $receipt) {
            $payout = Payout::query()
                ->whereKey($payout->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($payout->trader_id !== $trader->id) {
                throw PayoutException::payoutNotAssignedToTrader();
            }

            if ($payout->status->equals(PayoutStatus::COMPLETED)) {
                throw PayoutException::payoutAlreadyCompleted();
            }

            if ($payout->status->equals(PayoutStatus::SENT)) {
                throw PayoutException::payoutAlreadyMarkedAsSent();
            }

            if ($payout->status->notEquals(PayoutStatus::TAKEN)) {
                throw PayoutException::invalidPayoutStatus();
            }

            $now = now();
            $holdMinutes = max((int) ($trader->payout_hold_minutes ?? 60), 1);
            $holdUntil = $trader->payout_hold_enabled
                ? $now->copy()->addMinutes($holdMinutes)
                : null;

            $receiptPath = $receipt
                ? $this->storeReceipt($receipt, $payout->receipt_path)
                : $payout->receipt_path;

            $updatePayload = [
                'status' => PayoutStatus::SENT,
                'sent_at' => $now,
                'hold_until' => $holdUntil,
            ];

            if ($receiptPath) {
                $updatePayload['receipt_path'] = $receiptPath;
            }

            $payout->update($updatePayload);

            $this->logOperation($payout, PayoutOperationType::MARK_SENT, $payout->trader_credit, [
                'hold_enabled' => (bool) $trader->payout_hold_enabled,
                'hold_minutes' => $trader->payout_hold_enabled ? $holdMinutes : null,
                'hold_until' => $updatePayload['hold_until']?->toIso8601String(),
            ]);

            if ($trader->payout_hold_enabled && $holdUntil) {
                $this->logOperation($payout, PayoutOperationType::SET_HOLD, $payout->trader_credit, [
                    'hold_until' => $holdUntil->toIso8601String(),
                ]);

                CreditPayoutToTraderJob::dispatch($payout)->delay($holdUntil);

                return $payout->load('merchant', 'paymentGateway', 'trader');
            }

            $this->completeAndCredit($payout);

            return $payout->fresh()->load('merchant', 'paymentGateway', 'trader');
        });
    }

    /**
     * Подтверждение мерчантом успешной выплаты: снимаем холд и зачисляем средства трейдеру.
     *
     * @throws PayoutException
     */
    public function confirmPaid(Payout $payout): Payout
    {
        return Transaction::run(function () use ($payout) {
            $locked = Payout::query()
                ->whereKey($payout->id)
                ->with(['merchant', 'paymentGateway', 'trader.wallet'])
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status->notEquals(PayoutStatus::SENT)) {
                throw PayoutException::invalidPayoutStatus();
            }

            if (! $locked->trader) {
                throw PayoutException::payoutNotAssignedToTrader();
            }

            $locked->update([
                'hold_until' => now(),
            ]);

            $this->logOperation(
                $locked,
                PayoutOperationType::RELEASE_HOLD,
                $locked->trader_credit,
                [
                    'manual' => true,
                    'trigger' => 'merchant_confirm_paid',
                ]
            );

            $this->completeAndCredit($locked);

            return $locked->fresh('merchant', 'paymentGateway', 'trader');
        });
    }

    /**
     * Завершить выплату и зачислить средства трейдеру.
     *
     * @throws PayoutException
     */
    public function completeAndCredit(Payout $payout): void
    {
        Transaction::run(function () use ($payout) {
            $payout->refresh()->loadMissing('trader.wallet', 'trader.teamLeader.wallet');

            if (! $payout->trader || ! $payout->trader->wallet) {
                throw PayoutException::payoutNotAssignedToTrader();
            }

            if ($payout->status->equals(PayoutStatus::COMPLETED)) {
                return;
            }

            $payout->update([
                'status' => PayoutStatus::COMPLETED,
                'completed_at' => now(),
                'hold_until' => $payout->hold_until ?? now(),
            ]);

            services()->wallet()->giveToBalance(
                walletID: $payout->trader->wallet->id,
                amount: $payout->trader_credit,
                transactionType: TransactionType::INCOME_FROM_SUCCESSFUL_PAYOUT,
                balanceType: BalanceType::TRUST
            );

            $this->logOperation($payout, PayoutOperationType::CREDIT_TRADER, $payout->trader_credit);

            $this->creditTeamLeader($payout);
        });
    }

    /**
     * Ручное изменение статуса администратором. Стараемся привести все побочные
     * эффекты (резервы, холды, начисления) к консистентному состоянию.
     *
     * @throws PayoutException
     */
    public function adminChangeStatus(Payout $payout, PayoutStatus $status, ?User $trader = null, ?string $note = null): Payout
    {
        return Transaction::run(function () use ($payout, $status, $trader, $note) {
            $locked = Payout::query()
                ->whereKey($payout->id)
                ->with(['merchant.user.wallet', 'trader.wallet', 'paymentGateway'])
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status->equals($status)) {
                return $locked;
            }

            $requiresTrader = in_array($status, [
                PayoutStatus::TAKEN,
                PayoutStatus::SENT,
                PayoutStatus::COMPLETED,
            ], true);

            if ($requiresTrader && ! $trader) {
                throw new PayoutException('Для выбранного статуса необходимо выбрать трейдера.');
            }

            if ($trader) {
                $locked->setRelation('trader', $trader);
                $trader->loadMissing('teamLeader');
            }

            if ($trader && ! $trader->wallet) {
                $trader->setRelation('wallet', services()->wallet()->create($trader));
            }

            $merchantWallet = $this->resolveMerchantWallet($locked->merchant);

            if ($locked->status->equals(PayoutStatus::COMPLETED) && ! $status->equals(PayoutStatus::COMPLETED)) {
                $this->rollbackTraderCredit($locked);
                $this->rollbackTeamLeaderCredit($locked);
            }

            $receiptResetPayload = $this->resolveReceiptResetPayload($locked, $status);

            if ($status->equals(PayoutStatus::CANCELED)) {
                if (! $locked->status->equals(PayoutStatus::CANCELED)) {
                    $this->refundMerchant($locked, $merchantWallet);
                    $this->logOperation(
                        $locked,
                        PayoutOperationType::RETURN_TO_MERCHANT,
                        $locked->merchant_debit,
                        [
                            'manual' => true,
                            'note' => $note,
                        ]
                    );
                }

                $locked->update(array_merge([
                    'status' => PayoutStatus::CANCELED,
                    'canceled_at' => now(),
                    'trader_id' => null,
                    'taken_at' => null,
                    'sent_at' => null,
                    'hold_until' => null,
                    'completed_at' => null,
                    'expires_at' => null,
                ], $receiptResetPayload));

                return $locked->fresh('merchant', 'paymentGateway', 'trader');
            }

            if ($locked->status->equals(PayoutStatus::CANCELED)) {
                $this->reserveMerchantFunds($locked, $merchantWallet);
                $this->logOperation(
                    $locked,
                    PayoutOperationType::RESERVE_FROM_MERCHANT,
                    $locked->merchant_debit,
                    [
                        'manual' => true,
                    ]
                );
            }

            if ($status->equals(PayoutStatus::OPEN)) {
                if (! $locked->status->equals(PayoutStatus::OPEN) && ! $locked->status->equals(PayoutStatus::CANCELED)) {
                    throw new PayoutException('В этот статус можно перейти только из отменённой или открытой выплаты.');
                }
                $expiresAt = $this->calculateExpiresAt($locked);

                $locked->update(array_merge([
                    'status' => PayoutStatus::OPEN,
                    'trader_id' => null,
                    'taken_at' => null,
                    'sent_at' => null,
                    'hold_until' => null,
                    'completed_at' => null,
                    'canceled_at' => null,
                    'expires_at' => $expiresAt,
                ], $receiptResetPayload));

                if ($expiresAt) {
                    ExpiresPayoutJob::dispatch($locked)->delay($expiresAt);
                }

                return $locked->fresh('merchant', 'paymentGateway', 'trader');
            }

            if ($status->equals(PayoutStatus::TAKEN)) {
                if ($trader) {
                    $this->applyTeamLeaderCalculation($locked, $trader);
                }

                $locked->update(array_merge([
                    'status' => PayoutStatus::TAKEN,
                    'trader_id' => $trader?->id,
                    'taken_at' => now(),
                    'sent_at' => null,
                    'hold_until' => null,
                    'completed_at' => null,
                    'canceled_at' => null,
                ], $receiptResetPayload));

                $this->logOperation(
                    $locked,
                    PayoutOperationType::MARK_TAKEN,
                    null,
                    [
                        'manual' => true,
                        'trader_id' => $trader?->id,
                        'note' => $note,
                    ]
                );

                return $locked->fresh('merchant', 'paymentGateway', 'trader');
            }

            if ($status->equals(PayoutStatus::SENT)) {
                if ($trader) {
                    $this->applyTeamLeaderCalculation($locked, $trader);
                }

                $holdMinutes = max((int) ($trader?->payout_hold_minutes ?? 60), 1);
                $holdEnabled = (bool) ($trader?->payout_hold_enabled ?? false);
                $holdUntil = $holdEnabled ? now()->addMinutes($holdMinutes) : null;

                $locked->update([
                    'status' => PayoutStatus::SENT,
                    'trader_id' => $trader?->id,
                    'taken_at' => $locked->taken_at ?? now(),
                    'sent_at' => now(),
                    'hold_until' => $holdUntil,
                    'completed_at' => null,
                    'canceled_at' => null,
                ]);

                $this->logOperation(
                    $locked,
                    PayoutOperationType::MARK_SENT,
                    $locked->trader_credit,
                    [
                        'manual' => true,
                        'hold_enabled' => $holdEnabled,
                        'hold_minutes' => $holdEnabled ? $holdMinutes : null,
                        'hold_until' => $holdUntil?->toIso8601String(),
                        'note' => $note,
                    ]
                );

                if ($holdEnabled && $holdUntil) {
                    $this->logOperation(
                        $locked,
                        PayoutOperationType::SET_HOLD,
                        $locked->trader_credit,
                        [
                            'hold_until' => $holdUntil->toIso8601String(),
                            'manual' => true,
                        ]
                    );

                    CreditPayoutToTraderJob::dispatch($locked)->delay($holdUntil);
                } else {
                    $this->completeAndCredit($locked);
                }

                return $locked->fresh('merchant', 'paymentGateway', 'trader');
            }

            if ($status->equals(PayoutStatus::COMPLETED)) {
                if ($trader) {
                    $this->applyTeamLeaderCalculation($locked, $trader);
                }

                $locked->update([
                    'trader_id' => $trader?->id,
                    'taken_at' => $locked->taken_at ?? now(),
                    'sent_at' => $locked->sent_at ?? now(),
                    'hold_until' => $locked->hold_until ?? now(),
                    'canceled_at' => null,
                ]);

                $this->completeAndCredit($locked);

                return $locked->fresh('merchant', 'paymentGateway', 'trader');
            }

            throw new PayoutException('Не удалось сменить статус выплаты.');
        });
    }

    private function ensureGatewaySupportsPayouts(PaymentGateway $gateway): void
    {
        if (! (bool) $gateway->is_active) {
            throw PayoutException::gatewayInactive();
        }

        if (! $gateway->is_payouts_enabled) {
            throw PayoutException::gatewayPayoutsDisabled();
        }
    }

    private function ensureMerchantCanCreatePayouts(Merchant $merchant): void
    {
        if (! $merchant->validated_at) {
            throw PayoutException::merchantIsUnderModeration();
        }

        if ($merchant->banned_at) {
            throw PayoutException::merchantBlocked();
        }

        if (! $merchant->active) {
            throw PayoutException::merchantDisabled();
        }
    }

    private function resolveMerchantWallet(Merchant $merchant): Wallet
    {
        $merchant->loadMissing('user.wallet');

        if (! $merchant->user) {
            throw PayoutException::merchantWalletMissing();
        }

        if (! $merchant->user->wallet) {
            $wallet = services()->wallet()->create($merchant->user);
            $merchant->user->setRelation('wallet', $wallet);
        }

        return $merchant->user->wallet;
    }

    private function logOperation(Payout $payout, PayoutOperationType $type, ?Money $amount, array $meta = []): void
    {
        $payout->operations()->create([
            'type' => $type,
            'amount' => $amount,
            'amount_currency' => $amount?->getCurrency()->getCode(),
            'meta' => $meta,
        ]);
    }

    private function reserveMerchantFunds(Payout $payout, Wallet $wallet): void
    {
        $available = services()->wallet()->getTotalAvailableBalance($wallet, BalanceType::MERCHANT);

        if ($available->lessThan($payout->merchant_debit)) {
            throw PayoutException::insufficientMerchantFunds();
        }

        services()->wallet()->takeFromBalance(
            walletID: $wallet->id,
            amount: $payout->merchant_debit,
            transactionType: TransactionType::PAYMENT_FOR_OPENED_PAYOUT,
            balanceType: BalanceType::MERCHANT
        );
    }

    private function refundMerchant(Payout $payout, Wallet $wallet): void
    {
        services()->wallet()->giveToBalance(
            walletID: $wallet->id,
            amount: $payout->merchant_debit,
            transactionType: TransactionType::REFUND_FOR_CANCELED_PAYOUT,
            balanceType: BalanceType::MERCHANT
        );
    }

    private function rollbackTraderCredit(Payout $payout): void
    {
        if (! $payout->trader) {
            return;
        }

        $wallet = $payout->trader->wallet ?? services()->wallet()->create($payout->trader);

        services()->wallet()->takeFromBalance(
            walletID: $wallet->id,
            amount: $payout->trader_credit,
            transactionType: TransactionType::ROLLBACK_INCOME_FROM_SUCCESSFUL_PAYOUT,
            balanceType: BalanceType::TRUST
        );

        $this->logOperation(
            $payout,
            PayoutOperationType::CREDIT_TRADER,
            $payout->trader_credit,
            [
                'manual' => true,
                'rollback' => true,
            ]
        );
    }

    private function creditTeamLeader(Payout $payout): void
    {
        if (! $payout->trader?->teamLeader) {
            return;
        }

        if (! $payout->teamlead_fee || $payout->teamlead_fee->equalsToZero()) {
            return;
        }

        $teamLeader = $payout->trader->teamLeader;
        $wallet = $teamLeader->wallet ?? services()->wallet()->create($teamLeader);

        services()->wallet()->giveToBalance(
            walletID: $wallet->id,
            amount: $payout->teamlead_fee,
            transactionType: TransactionType::INCOME_FROM_REFERRALS_SUCCESSFUL_PAYOUT,
            balanceType: BalanceType::TEAMLEADER
        );

        $this->logOperation(
            $payout,
            PayoutOperationType::TEAMLEAD_INCOME,
            $payout->teamlead_fee,
            [
                'team_leader_id' => $teamLeader->id,
            ]
        );
    }

    private function rollbackTeamLeaderCredit(Payout $payout): void
    {
        if (! $payout->trader?->teamLeader) {
            return;
        }

        if (! $payout->teamlead_fee || $payout->teamlead_fee->equalsToZero()) {
            return;
        }

        $teamLeader = $payout->trader->teamLeader;
        $wallet = $teamLeader->wallet ?? services()->wallet()->create($teamLeader);

        services()->wallet()->takeFromBalance(
            walletID: $wallet->id,
            amount: $payout->teamlead_fee,
            transactionType: TransactionType::ROLLBACK_INCOME_FROM_REFERRALS_SUCCESSFUL_PAYOUT,
            balanceType: BalanceType::TEAMLEADER
        );

        $this->logOperation(
            $payout,
            PayoutOperationType::TEAMLEAD_INCOME,
            $payout->teamlead_fee,
            [
                'manual' => true,
                'rollback' => true,
                'team_leader_id' => $teamLeader->id,
            ]
        );
    }

    private function calculateExpiresAt(Payout $payout): ?Carbon
    {
        $currency = $payout->amount_fiat?->getCurrency() ?? Currency::RUB();
        $settings = $this->resolvePayoutSettings($payout->paymentGateway, $currency);
        $reservationMinutes = $settings['reservation_minutes'];

        if ($reservationMinutes <= 0) {
            return null;
        }

        return now()->addMinutes($reservationMinutes);
    }

    /**
     * @return array{total_rate: float, trader_rate: float, reservation_minutes: int}
     */
    private function resolvePayoutSettings(?PaymentGateway $gateway, Currency $currency): array
    {
        if ($gateway) {
            return [
                'total_rate' => (float) $gateway->total_service_commission_rate_for_payouts,
                'trader_rate' => (float) $gateway->trader_commission_rate_for_payouts,
                'reservation_minutes' => (int) ($gateway->reservation_time_for_payouts ?? 30),
            ];
        }

        $settings = services()->settings()->getPayoutSettingsForCurrency($currency);

        return [
            'total_rate' => (float) ($settings['total_commission_rate'] ?? 0),
            'trader_rate' => (float) ($settings['trader_commission_rate'] ?? 0),
            'reservation_minutes' => (int) ($settings['reservation_time_for_payouts'] ?? 0),
        ];
    }

    private function applyTeamLeaderCalculation(Payout $payout, User $trader): void
    {
        if (! $payout->amount_fiat || ! $payout->conversion_price) {
            return;
        }

        $teamLeaderRate = (float) ($trader->teamLeader?->payout_referral_commission_percentage
            ?? $trader->teamLeader?->referral_commission_percentage
            ?? 0);
        $splitFromServicePercent = (float) ($trader->teamLeader?->payout_team_leader_split_from_service_percent
            ?? $trader->teamLeader?->team_leader_split_from_service_percent
            ?? 0);
        $splitFromTraderPercent = max(0, 100 - $splitFromServicePercent);

        $profits = services()->profit()->calculateOutBody(
            sourceAmount: $payout->amount_fiat,
            exchangeRate: $payout->conversion_price,
            totalFeeRate: (float) $payout->total_commission_rate,
            traderFeeRate: (float) $payout->trader_commission_rate,
            teamLeaderFeeRate: $teamLeaderRate,
            teamLeaderServiceSplitPercent: $splitFromServicePercent
        );

        $payout->update([
            'usdt_body' => $profits->convertedAmount,
            'total_fee' => $profits->totalFee,
            'trader_fee' => $profits->traderFee,
            'teamlead_fee' => $profits->teamLeaderFee,
            'service_fee' => $profits->serviceFee,
            'merchant_debit' => $profits->merchantDebit,
            'trader_credit' => $profits->traderCredit,
            'teamlead_commission_rate' => $teamLeaderRate,
            'teamlead_split_from_service_percent' => $splitFromServicePercent,
            'teamlead_split_from_trader_percent' => $splitFromTraderPercent,
        ]);
    }

    private function storeReceipt(UploadedFile $file, ?string $existingPath = null): string
    {
        if ($existingPath) {
            $this->deleteReceipt($existingPath);
        }

        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
        $filename = (string) Str::uuid();
        if ($extension !== '') {
            $filename .= '.' . $extension;
        }

        return $file->storeAs(
            self::RECEIPT_DIRECTORY,
            $filename,
            ['disk' => self::RECEIPT_DISK]
        );
    }

    private function deleteReceipt(?string $path): void
    {
        if (! $path) {
            return;
        }

        Storage::disk(self::RECEIPT_DISK)->delete($path);
    }

    /**
     * @throws PayoutException
     */
    private function resolveGeoMarket(Merchant $merchant, Currency $currency): MarketEnum
    {
        $geoMap = $merchant->getGeoMap();
        $marketValue = $geoMap[$currency->getCode()] ?? $geoMap[strtolower($currency->getCode())] ?? null;

        if (! $marketValue) {
            throw PayoutException::geoNotConfigured(strtoupper($currency->getCode()));
        }

        $market = MarketEnum::tryFrom($marketValue);

        if (! $market) {
            throw PayoutException::geoNotConfigured(strtoupper($currency->getCode()));
        }

        $supportsCurrency = services()->market()
            ->getSupportedCurrencies($market)
            ->contains(fn (Currency $supported) => $supported->getCode() === $currency->getCode());

        if (! $supportsCurrency) {
            throw PayoutException::geoUnsupported(strtoupper($currency->getCode()), $market->value);
        }

        return $market;
    }

    /**
     * Сбросить чек, если админ откатывает выплату из статуса "деньги отправлены".
     */
    private function resolveReceiptResetPayload(Payout $payout, PayoutStatus $targetStatus): array
    {
        $shouldReset = $payout->receipt_path
            && $payout->status->equals(PayoutStatus::SENT)
            && ! in_array($targetStatus, [PayoutStatus::SENT, PayoutStatus::COMPLETED], true);

        if (! $shouldReset) {
            return [];
        }

        $this->deleteReceipt($payout->receipt_path);
        $payout->receipt_path = null;

        return ['receipt_path' => null];
    }
}

