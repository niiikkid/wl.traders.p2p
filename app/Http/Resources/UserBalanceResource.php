<?php

namespace App\Http\Resources;

use App\Enums\BalanceType;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use App\Services\Money\Currency;
use App\Services\Money\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserBalanceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /**
         * @var User $this
         */

        // Используем предварительно вычисленные данные из контроллера
        $balanceStats = $this->balance_stats ?? [
            'trust_deposits' => 0,
            'trust_withdrawals' => 0,
            'merchant_deposits' => 0, 
            'merchant_withdrawals' => 0,
            'teamleader_deposits' => 0,
            'teamleader_withdrawals' => 0,
            'payment_for_orders' => 0,
        ];

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'avatar_uuid' => $this->avatar_uuid,
            'avatar_style' => $this->avatar_style,
            'banned_at' => $this->banned_at,
            'created_at' => $this->created_at?->format('d.m.Y H:i'),
            'role' => [
                'id' => $this->roles->first()?->id,
                'name' => $this->roles->first()?->name,
            ],
            'wallet' => [
                'id' => $this->wallet?->id,
                'trust_balance' => $this->wallet->trust_balance->add($this->wallet->reserve_balance)->toBeauty(),
                'merchant_balance' => $this->wallet->merchant_balance->toBeauty(),
                'teamleader_balance' => $this->wallet->teamleader_balance->toBeauty(),
                'total_balance' => $this->wallet->trust_balance
                        ->add($this->wallet->merchant_balance)
                        ->add($this->wallet->reserve_balance)
                        ->add($this->wallet->teamleader_balance)
                        ->toBeauty(),
                'trust_deposits' => Money::fromUnits($balanceStats['trust_deposits'], Currency::USDT())->toBeauty(),
                'trust_withdrawals' => Money::fromUnits($balanceStats['trust_withdrawals'], Currency::USDT())->toBeauty(),
                'merchant_deposits' => Money::fromUnits($balanceStats['merchant_deposits'], Currency::USDT())->toBeauty(),
                'merchant_withdrawals' => Money::fromUnits($balanceStats['merchant_withdrawals'], Currency::USDT())->toBeauty(),
                'teamleader_deposits' => Money::fromUnits($balanceStats['teamleader_deposits'], Currency::USDT())->toBeauty(),
                'teamleader_withdrawals' => Money::fromUnits($balanceStats['teamleader_withdrawals'], Currency::USDT())->toBeauty(),
                'payment_for_orders' => Money::fromUnits($balanceStats['payment_for_orders'], Currency::USDT())->toBeauty(),
            ],
        ];
    }
}
