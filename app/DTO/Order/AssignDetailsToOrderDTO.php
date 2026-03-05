<?php

namespace App\DTO\Order;

use App\DTO\BaseDTO;
use App\Enums\DetailType;
use App\Models\PaymentGateway;

readonly class AssignDetailsToOrderDTO extends BaseDTO
{
    public function __construct(
        public ?PaymentGateway $gateway = null,
        public ?DetailType $detailType = null,
    )
    {}
}
