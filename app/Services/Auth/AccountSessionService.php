<?php

namespace App\Services\Auth;

use App\Contracts\AccountSessionServiceContract;
use App\Facades\LoginLogger;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FALaravel\Google2FA;

class AccountSessionService implements AccountSessionServiceContract
{
    private const ACCOUNTS_KEY = 'auth.account_sessions.accounts';

    private const PENDING_KEY = 'auth.account_sessions.pending';

    private const PENDING_TTL_SECONDS = 300;

    public function ensureCurrentAccount(Request $request, User $user, bool $remember = false): void
    {
        $accounts = $this->accounts($request);
        $key = (string) $user->id;

        if (! isset($accounts[$key])) {
            $this->rememberCurrentAccount($request, $user, $remember);

            return;
        }

        $account = $accounts[$key];
        $twoFactorPassed = $this->accountTwoFactorPassed($account, $user)
            || ($request->session()->get('user_2fa_passed') === true && $user->google2fa_secret !== null)
            || $user->google2fa_secret === null;

        $accounts[$key] = [
            ...$this->accountPayload(
                user: $user,
                remember: $remember || (bool) ($account['remember'] ?? false),
                twoFactorPassed: $twoFactorPassed,
            ),
            'added_at' => $account['added_at'] ?? now()->toISOString(),
            'last_switched_at' => $account['last_switched_at'] ?? now()->toISOString(),
        ];

        $this->putAccounts($request, $accounts);
        $this->syncActiveSessionMarkers($request, $user);
    }

    public function rememberCurrentAccount(Request $request, User $user, bool $remember = false, ?bool $twoFactorPassed = null): void
    {
        $accounts = $this->accounts($request);
        $key = (string) $user->id;
        $existing = $accounts[$key] ?? [];

        $accounts[$key] = [
            ...$this->accountPayload(
                user: $user,
                remember: $remember || (bool) ($existing['remember'] ?? false),
                twoFactorPassed: $twoFactorPassed ?? $this->resolvedCurrentTwoFactorState($request, $user),
            ),
            'added_at' => $existing['added_at'] ?? now()->toISOString(),
            'last_switched_at' => now()->toISOString(),
        ];

        $this->putAccounts($request, $accounts);
        $this->syncActiveSessionMarkers($request, $user);
    }

    public function accountsForShare(Request $request, ?User $currentUser): array
    {
        if (! $currentUser instanceof User) {
            return [
                'items' => [],
                'has_multiple' => false,
            ];
        }

        $this->ensureCurrentAccount($request, $currentUser);

        $accounts = $this->accounts($request);
        $ids = collect(array_keys($accounts))
            ->map(fn (string $id): int => (int) $id)
            ->filter()
            ->values();

        $users = User::query()
            ->with('roles')
            ->whereIn('id', $ids)
            ->whereNull('archived_at')
            ->get()
            ->keyBy('id');

        $items = [];

        foreach ($accounts as $key => $account) {
            /** @var User|null $user */
            $user = $users->get((int) $key);

            if (! $user instanceof User) {
                unset($accounts[$key]);

                continue;
            }

            $role = $user->roles->first();

            $items[] = [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'login' => $user->email,
                'avatar_url' => $user->avatarUrl(),
                'role' => $role ? [
                    'id' => $role->id,
                    'name' => $role->name,
                ] : null,
                'is_current' => $currentUser->id === $user->id,
                'has_2fa' => $user->google2fa_secret !== null,
                'two_factor_passed' => $this->accountTwoFactorPassed($account, $user),
                'added_at' => $account['added_at'] ?? null,
                'last_switched_at' => $account['last_switched_at'] ?? null,
            ];
        }

        $this->putAccounts($request, $accounts);

        return [
            'items' => $items,
            'has_multiple' => count($items) > 1,
        ];
    }

    public function addAndSwitch(Request $request, User $user, bool $remember, bool $twoFactorPassed): void
    {
        $this->rememberCurrentAccount($request, $user, $remember, $twoFactorPassed);
        $this->switchToAccount($request, $user, true);
    }

    public function preparePendingAdd(Request $request, User $user, bool $remember): void
    {
        $request->session()->put(self::PENDING_KEY, [
            'type' => 'add',
            'user_id' => $user->id,
            'remember' => $remember,
            'password_fingerprint' => $this->passwordFingerprint($user),
            'created_at' => time(),
        ]);
    }

    public function preparePendingSwitch(Request $request, User $user): void
    {
        $this->assertAccountIsStored($request, $user);

        $request->session()->put(self::PENDING_KEY, [
            'type' => 'switch',
            'user_id' => $user->id,
            'remember' => (bool) ($this->accounts($request)[(string) $user->id]['remember'] ?? false),
            'password_fingerprint' => $this->passwordFingerprint($user),
            'created_at' => time(),
        ]);
    }

    public function pendingAccountForShare(Request $request): ?array
    {
        $pending = $this->pending($request);

        if ($pending === null) {
            return null;
        }

        $user = $this->findAvailableUser((int) $pending['user_id']);

        if (! $user instanceof User) {
            $request->session()->forget(self::PENDING_KEY);

            return null;
        }

        $role = $user->roles->first();

        return [
            'id' => $user->id,
            'email' => $user->email,
            'login' => $user->email,
            'avatar_url' => $user->avatarUrl(),
            'role' => $role ? [
                'id' => $role->id,
                'name' => $role->name,
            ] : null,
            'type' => $pending['type'],
        ];
    }

    public function completePendingTwoFactor(Request $request, string $code): User
    {
        $pending = $this->pendingOrFail($request);
        $user = $this->findAvailableUser((int) $pending['user_id']);

        if (! $user instanceof User) {
            $request->session()->forget(self::PENDING_KEY);

            throw ValidationException::withMessages([
                'one_time_password' => 'Аккаунт больше недоступен для входа.',
            ]);
        }

        if (! hash_equals((string) $pending['password_fingerprint'], $this->passwordFingerprint($user))) {
            $request->session()->forget(self::PENDING_KEY);
            $this->removeAccount($request, $user);

            throw ValidationException::withMessages([
                'one_time_password' => 'Сессия входа устарела. Добавьте аккаунт заново.',
            ]);
        }

        if (! $this->verifyTwoFactorCode($user, $code)) {
            throw ValidationException::withMessages([
                'one_time_password' => 'Неверный 2FA код.',
            ]);
        }

        if ($pending['type'] === 'add') {
            $this->addAndSwitch($request, $user, (bool) ($pending['remember'] ?? false), true);
        } else {
            $this->markAccountTwoFactorPassed($request, $user);
            $this->switchToAccount($request, $user);
        }

        $request->session()->forget(self::PENDING_KEY);

        return $user;
    }

    public function switchToAccount(Request $request, User $user, bool $recordLogin = false): void
    {
        $account = $this->assertAccountIsStored($request, $user);

        if (! hash_equals((string) ($account['password_fingerprint'] ?? ''), $this->passwordFingerprint($user))) {
            $this->removeAccount($request, $user);

            throw ValidationException::withMessages([
                'account' => 'Сессия этого аккаунта устарела. Войдите в него заново.',
            ]);
        }

        if ($this->requiresTwoFactor($request, $user)) {
            throw ValidationException::withMessages([
                'account' => 'Для этого аккаунта требуется подтверждение 2FA.',
            ]);
        }

        $remember = (bool) ($account['remember'] ?? false);
        $guard = Auth::guard('web');

        if (! $remember && method_exists($guard, 'getRecallerName')) {
            Cookie::queue(Cookie::forget($guard->getRecallerName()));
        }

        if (! $recordLogin) {
            LoginLogger::disable();
        }

        try {
            $guard->login($user, $remember);
        } finally {
            if (! $recordLogin) {
                LoginLogger::enable();
            }
        }

        $accounts = $this->accounts($request);
        $accounts[(string) $user->id] = [
            ...$this->accountPayload($user, $remember, $this->currentTwoFactorPassed($request, $user)),
            'added_at' => $account['added_at'] ?? now()->toISOString(),
            'last_switched_at' => now()->toISOString(),
        ];

        $this->putAccounts($request, $accounts);
        $this->syncActiveSessionMarkers($request, $user);
    }

    public function removeAccount(Request $request, User $user): void
    {
        $accounts = $this->accounts($request);
        unset($accounts[(string) $user->id]);
        $this->putAccounts($request, $accounts);
    }

    public function markCurrentTwoFactorPassed(Request $request, User $user): void
    {
        $this->markAccountTwoFactorPassed($request, $user);
        $this->syncActiveSessionMarkers($request, $user);
    }

    public function currentTwoFactorPassed(Request $request, User $user): bool
    {
        if ($user->google2fa_secret === null) {
            return true;
        }

        $account = $this->accounts($request)[(string) $user->id] ?? null;

        if (is_array($account) && $this->accountTwoFactorPassed($account, $user)) {
            return true;
        }

        return Auth::id() === $user->id && $request->session()->get('user_2fa_passed') === true;
    }

    public function requiresTwoFactor(Request $request, User $user): bool
    {
        return $user->google2fa_secret !== null && ! $this->currentTwoFactorPassed($request, $user);
    }

    public function verifyTwoFactorCode(User $user, string $code): bool
    {
        if ($user->google2fa_secret === null) {
            return true;
        }

        /** @var Google2FA $google2fa */
        $google2fa = app('pragmarx.google2fa');

        return hash_equals(
            (string) $google2fa->getCurrentOtp($user->google2fa_secret),
            trim($code),
        );
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function accounts(Request $request): array
    {
        $accounts = $request->session()->get(self::ACCOUNTS_KEY, []);

        return is_array($accounts) ? $accounts : [];
    }

    /**
     * @param  array<string, array<string, mixed>>  $accounts
     */
    private function putAccounts(Request $request, array $accounts): void
    {
        $request->session()->put(self::ACCOUNTS_KEY, $accounts);
    }

    /**
     * @return array<string, mixed>
     */
    private function accountPayload(User $user, bool $remember, bool $twoFactorPassed): array
    {
        return [
            'user_id' => $user->id,
            'remember' => $remember,
            'password_fingerprint' => $this->passwordFingerprint($user),
            'two_factor_passed' => $user->google2fa_secret === null || $twoFactorPassed,
            'two_factor_fingerprint' => $this->twoFactorFingerprint($user),
        ];
    }

    /**
     * @param  array<string, mixed>  $account
     */
    private function accountTwoFactorPassed(array $account, User $user): bool
    {
        if ($user->google2fa_secret === null) {
            return true;
        }

        return (bool) ($account['two_factor_passed'] ?? false)
            && hash_equals((string) ($account['two_factor_fingerprint'] ?? ''), (string) $this->twoFactorFingerprint($user));
    }

    private function resolvedCurrentTwoFactorState(Request $request, User $user): bool
    {
        return $user->google2fa_secret === null || $request->session()->get('user_2fa_passed') === true;
    }

    private function markAccountTwoFactorPassed(Request $request, User $user): void
    {
        $accounts = $this->accounts($request);
        $account = $accounts[(string) $user->id] ?? [];

        $accounts[(string) $user->id] = [
            ...$this->accountPayload($user, (bool) ($account['remember'] ?? false), true),
            'added_at' => $account['added_at'] ?? now()->toISOString(),
            'last_switched_at' => $account['last_switched_at'] ?? now()->toISOString(),
        ];

        $this->putAccounts($request, $accounts);
    }

    /**
     * @return array<string, mixed>
     */
    private function assertAccountIsStored(Request $request, User $user): array
    {
        $account = $this->accounts($request)[(string) $user->id] ?? null;

        if (! is_array($account)) {
            throw ValidationException::withMessages([
                'account' => 'Этот аккаунт не добавлен в текущем браузере.',
            ]);
        }

        if ($user->archived_at !== null) {
            $this->removeAccount($request, $user);

            throw ValidationException::withMessages([
                'account' => 'Аккаунт больше недоступен для входа.',
            ]);
        }

        return $account;
    }

    private function findAvailableUser(int $userId): ?User
    {
        return User::query()
            ->with('roles')
            ->whereKey($userId)
            ->whereNull('archived_at')
            ->first();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function pending(Request $request): ?array
    {
        $pending = $request->session()->get(self::PENDING_KEY);

        if (! is_array($pending) || ! isset($pending['user_id'], $pending['type'], $pending['created_at'])) {
            return null;
        }

        if ((int) $pending['created_at'] + self::PENDING_TTL_SECONDS < time()) {
            $request->session()->forget(self::PENDING_KEY);

            return null;
        }

        return $pending;
    }

    /**
     * @return array<string, mixed>
     */
    private function pendingOrFail(Request $request): array
    {
        $pending = $this->pending($request);

        if ($pending === null) {
            throw ValidationException::withMessages([
                'one_time_password' => 'Сессия подтверждения истекла. Повторите вход.',
            ]);
        }

        return $pending;
    }

    private function syncActiveSessionMarkers(Request $request, User $user): void
    {
        $request->session()->put('password_hash_'.Auth::getDefaultDriver(), $user->getAuthPassword());

        if ($this->currentTwoFactorPassed($request, $user)) {
            $request->session()->put('user_2fa_passed', true);

            return;
        }

        $request->session()->forget('user_2fa_passed');
    }

    private function passwordFingerprint(User $user): string
    {
        return $this->fingerprint($user->getAuthPassword());
    }

    private function twoFactorFingerprint(User $user): ?string
    {
        if ($user->google2fa_secret === null) {
            return null;
        }

        return $this->fingerprint($user->google2fa_secret);
    }

    private function fingerprint(string $value): string
    {
        return hash_hmac('sha256', $value, (string) config('app.key'));
    }
}
