<?php

namespace App\Contracts;

use App\Models\User;
use App\Models\UserLoginHistory;
use Illuminate\Http\Request;

interface LoginHistoryServiceContract
{
    /**
     * Записывает информацию о входе пользователя в систему
     *
     * @param User $user
     * @param Request $request
     * @param bool $isSuccessful
     * @return UserLoginHistory
     */
    public function recordLogin(User $user, Request $request, bool $isSuccessful = true): UserLoginHistory;
}
