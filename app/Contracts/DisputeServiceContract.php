<?php

namespace App\Contracts;

use App\Enums\DisputeCancelReasonCode;
use App\Exceptions\DisputeException;
use App\Models\Dispute;
use Illuminate\Http\UploadedFile;

interface DisputeServiceContract
{
    /**
     * @throws DisputeException
     */
    public function create(int $orderID, ?UploadedFile $receipt = null): Dispute;

    /**
     * @throws DisputeException
     */
    public function accept(int $disputeID): bool;

    /**
     * @throws DisputeException
     */
    public function cancel(
        int $disputeID,
        DisputeCancelReasonCode $reasonCode,
        ?string $customReason = null,
        ?UploadedFile $bankStatement = null,
    ): bool;

    /**
     * @throws DisputeException
     */
    public function rollback(int $disputeID): bool;
}
