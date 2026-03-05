<?php

namespace App\Services\Order;

use App\Contracts\OrderServiceContract;
use App\Enums\OrderSubStatus;
use App\Models\Order;
use App\Services\Money\Money;
use App\DTO\Order\CreateOrderDTO;
use App\DTO\Order\AssignDetailsToOrderDTO;
use App\Services\Order\Features\OrderDetailAssigner;
use App\Services\Order\Features\OrderMaker;
use App\Services\Order\Features\OrderOperator;
use App\Utils\Transaction;

class OrderService implements OrderServiceContract
{
    public function create(CreateOrderDTO $data): Order
    {
        return $this->transaction(function () use ($data) {
            $order = (new OrderMaker($data))->create();

            if (! $data->manually) {
                $order = (new OrderDetailAssigner(
                    order: $order,
                    data: new AssignDetailsToOrderDTO(
                        gateway: $data->paymentGateway,
                        detailType: $data->paymentDetailType,
                    )
                ))->assign();
            }

            return $order;
        });
    }

    public function assignDetailsToOrder(int $orderID, AssignDetailsToOrderDTO $data): Order
    {
        return $this->transaction(function () use ($orderID, $data) {
            $order = Order::withoutGlobalScopes()->where('id', $orderID)->lockForUpdate()->first();

            return (new OrderDetailAssigner($order, $data))->assign();
        });
    }

    public function finishOrderAsSuccessful(int $orderID, OrderSubStatus $subStatus): void
    {
        $this->transaction(function () use ($orderID, $subStatus) {
            (new OrderOperator($orderID))->finishOrderAsSuccessful($subStatus);
        });
    }

    public function finishOrderAsFailed(int $orderID, OrderSubStatus $subStatus): void
    {
        $this->transaction(function () use ($orderID, $subStatus) {
            (new OrderOperator($orderID))->finishOrderAsFailed($subStatus);
        });
    }

    public function reopenFinishedOrder(int $orderID, OrderSubStatus $subStatus): void
    {
        $this->transaction(function () use ($orderID, $subStatus) {
            (new OrderOperator($orderID))->reopenFinishedOrder($subStatus);
        });
    }

    public function updateAmount(int $orderID, Money $amount): void
    {
        $this->transaction(function () use ($orderID, $amount) {
            (new OrderOperator($orderID))->updateAmount($amount);
        });
    }

    protected function transaction(callable $callback): mixed
    {
        return Transaction::run(function () use ($callback) {
            return $callback();
        });
    }
}
