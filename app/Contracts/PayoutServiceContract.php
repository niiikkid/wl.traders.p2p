<?php

namespace App\Contracts;

use App\DTO\Payout\PayoutCreateDTO;
use App\Enums\PayoutStatus;
use App\Exceptions\PayoutException;
use App\Models\Payout\Payout;
use App\Models\User;
use Illuminate\Http\UploadedFile;

interface PayoutServiceContract
{
    /**
     * @throws PayoutException
     */
    public function create(PayoutCreateDTO $data): Payout;

    /**
     * @throws PayoutException
     */
    public function cancel(Payout $payout): Payout;

    /**
     * @throws PayoutException
     */
    public function take(Payout $payout, User $trader): Payout;

    /**
     * @throws PayoutException
     */
    public function markSent(Payout $payout, User $trader, ?UploadedFile $receipt = null): Payout;

    /**
     * @throws PayoutException
     */
    public function confirmPaid(Payout $payout): Payout;

    /**
     * Ручное изменение статуса администратором с учётом побочных эффектов.
     *
     * @throws PayoutException
     */
    public function adminChangeStatus(Payout $payout, PayoutStatus $status, ?User $trader = null, ?string $note = null): Payout;
}

