<?php

namespace App\Contracts;

use App\Models\User;
use App\Models\UserLoginHistory;
use Illuminate\Http\Request;

interface LoginHistoryServiceContract
{
    /**
     * Записывает информацию о входе пользователя в систему
     */
    public function recordLogin(User $user, Request $request, bool $isSuccessful = true): UserLoginHistory;

    public function isLoggingEnabledFor(User $user): bool;

    public function clearUserHistory(User $user): void;
}
