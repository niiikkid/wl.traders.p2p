<?php

namespace App\Contracts;

use App\Models\User;

interface MainPageStatsServiceContract
{
    public function buildMerchantStats(User $user): array;

    public function buildTraderStats(User $user): array;

    public function buildLeaderStats(User $user): array;

    public function buildAdminStats(
        User $user,
        ?int $merchantId = null,
        string $periodPreset = 'all',
        ?string $dateFrom = null,
        ?string $dateTo = null,
        array $filters = [],
    ): array;

    public function buildTraderMainPageStats(
        User $user,
        string $periodPreset = 'all',
        ?string $dateFrom = null,
        ?string $dateTo = null,
        array $filters = [],
    ): array;
}

