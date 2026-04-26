<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DTO\Cascade\CreateCascadeDealDTO;
use App\Models\CascadeDeal;

interface CascadeServiceContract
{
    public function createDeal(CreateCascadeDealDTO $dto): CascadeDeal;

    public function findDealByExternalId(string $merchantUuid, string $externalId): CascadeDeal;

    public function cancelDeal(CascadeDeal $cascadeDeal): CascadeDeal;

    /**
     * @return array<string, mixed>
     */
    public function storeConfirmationCode(CascadeDeal $cascadeDeal, string $confirmationCode): array;
}
