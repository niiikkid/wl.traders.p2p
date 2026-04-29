<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DTO\Cascade\CreateCascadeDealDTO;
use App\Models\CascadeDeal;
use App\Models\CascadeProvider;
use Illuminate\Http\Request;

interface CascadeServiceContract
{
    public function createDeal(CreateCascadeDealDTO $dto): CascadeDeal;

    public function findDealByExternalId(string $merchantUuid, string $externalId): CascadeDeal;

    public function cancelDeal(CascadeDeal $cascadeDeal): CascadeDeal;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function openDispute(CascadeDeal $cascadeDeal, array $data): array;

    /**
     * @return array<string, mixed>
     */
    public function getDispute(CascadeDeal $cascadeDeal): array;

    /**
     * @return array<string, mixed>
     */
    public function storeConfirmationCode(CascadeDeal $cascadeDeal, string $confirmationCode): array;

    /**
     * @return array<string, mixed>
     */
    public function handleProviderCallback(Request $request, CascadeProvider $cascadeProvider): array;
}
