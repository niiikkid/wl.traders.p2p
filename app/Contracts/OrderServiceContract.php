<?php

namespace App\Contracts;

use App\DTO\Order\CreateOrderDTO;
use App\DTO\Order\AssignDetailsToOrderDTO;
use App\Enums\OrderSubStatus;
use App\Exceptions\OrderException;
use App\Models\Order;
use App\Services\Money\Money;

interface OrderServiceContract
{
    /**
     * @throws OrderException
     */
    public function create(CreateOrderDTO $data): Order;

    /**
     * @throws OrderException
     */
    public function assignDetailsToOrder(int $orderID, AssignDetailsToOrderDTO $data): Order;

    /**
     * @throws OrderException
     */
    public function finishOrderAsSuccessful(int $orderID, OrderSubStatus $subStatus): void;

    /**
     * @throws OrderException
     */
    public function finishOrderAsFailed(int $orderID, OrderSubStatus $subStatus): void;

    /**
     * @throws OrderException
     */
    public function reopenFinishedOrder(int $orderID, OrderSubStatus $subStatus): void;

    /**
     * @throws OrderException
     */
    public function updateAmount(int $orderID, Money $amount): void;
}
