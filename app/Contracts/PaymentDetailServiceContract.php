<?php

namespace App\Contracts;

use App\DTO\PaymentDetail\PaymentDetailCreateDTO;
use App\DTO\PaymentDetail\PaymentDetailUpdateDTO;
use App\Models\PaymentDetail;
use App\Models\User;

interface PaymentDetailServiceContract
{
    public function create(PaymentDetailCreateDTO $data): PaymentDetail;

    public function update(PaymentDetailUpdateDTO $data, PaymentDetail $paymentDetail): PaymentDetail;

    public function toggleActive(PaymentDetail $paymentDetail): void;

    public function restoreVipLimitsForUser(User $user): void;

    public function resetVipLimitsForUser(User $user): void;
}


