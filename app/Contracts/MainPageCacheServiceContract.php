<?php

namespace App\Contracts;

use App\Models\User;

interface MainPageCacheServiceContract
{
    public function rememberMerchant(User $user): array;

    public function rememberTrader(User $user): array;

    public function rememberLeader(User $user): array;

    public function rememberAdmin(User $user, ?int $merchantId = null): array;

    public function cacheByViewMode(User $user, string $viewMode, ?int $merchantId = null): array;
}

