<?php

namespace Database\Seeders;

use App\Models\Order;
use Illuminate\Database\Seeder;
use Throwable;

class RecalculateServiceProfitSeeder extends Seeder
{
    public function run(): void
    {
        $maxOrderId = (int) Order::query()->max('id');

        if ($maxOrderId === 0) {
            return;
        }

        $lastOrderId = $maxOrderId;

        Order::query()
            ->where('id', '<=', $lastOrderId)
            ->orderBy('id')
            ->chunkById(200, function ($orders) {
                foreach ($orders as $order) {
                    dispatch(function () use ($order) {
                        $freshOrder = Order::query()->find($order->id);

                        if (! $freshOrder) {
                            return;
                        }

                        if (! $freshOrder->amount || ! $freshOrder->conversion_price) {
                            return;
                        }

                        if (
                            $freshOrder->total_service_commission_rate === null
                            || $freshOrder->trader_commission_rate === null
                        ) {
                            return;
                        }

                        try {
                            $team_leader_split_from_service_percent = $freshOrder->team_leader_split_from_service_percent;

                            if ($freshOrder->team_leader_commission_rate && $team_leader_split_from_service_percent === null) {
                                $team_leader_split_from_service_percent = 100.0;
                            }

                            $profits = services()->profit()->calculateInBody(
                                sourceAmount: $freshOrder->amount,
                                exchangeRate: $freshOrder->conversion_price,
                                totalFeeRate: (float) $freshOrder->total_service_commission_rate,
                                traderFeeRate: (float) $freshOrder->trader_commission_rate,
                                teamLeaderFeeRate: $freshOrder->team_leader_commission_rate,
                                teamLeaderServiceSplitPercent: $team_leader_split_from_service_percent
                            );

                            $freshOrder->update([
                                'service_profit' => $profits->serviceFee,
                                'total_fee' => $profits->totalFee,
                                'team_leader_split_from_service_percent' => $team_leader_split_from_service_percent,
                            ]);
                        } catch (Throwable $exception) {
                            logger()->error('Recalculate service profit failed.', [
                                'order_id' => $order->id,
                            ]);
                            report($exception);
                        }
                    });
                }
            });
    }
}
