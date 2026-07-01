<?php

namespace App\Contracts;

use App\Models\User;
use Illuminate\Http\Request;

interface AccountSessionServiceContract
{
    public function ensureCurrentAccount(Request $request, User $user, bool $remember = false): void;

    public function rememberCurrentAccount(Request $request, User $user, bool $remember = false, ?bool $twoFactorPassed = null): void;

    /**
     * @return array{items: array<int, array<string, mixed>>, has_multiple: bool}
     */
    public function accountsForShare(Request $request, ?User $currentUser): array;

    public function addAndSwitch(Request $request, User $user, bool $remember, bool $twoFactorPassed): void;

    public function preparePendingAdd(Request $request, User $user, bool $remember): void;

    public function preparePendingSwitch(Request $request, User $user): void;

    /**
     * @return array<string, mixed>|null
     */
    public function pendingAccountForShare(Request $request): ?array;

    public function completePendingTwoFactor(Request $request, string $code): User;

    public function switchToAccount(Request $request, User $user, bool $recordLogin = false): void;

    public function removeAccount(Request $request, User $user): void;

    public function markCurrentTwoFactorPassed(Request $request, User $user): void;

    public function currentTwoFactorPassed(Request $request, User $user): bool;

    public function requiresTwoFactor(Request $request, User $user): bool;

    public function verifyTwoFactorCode(User $user, string $code): bool;
}
