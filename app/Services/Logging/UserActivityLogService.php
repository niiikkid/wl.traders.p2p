<?php

namespace App\Services\Logging;

use App\Contracts\UserActivityLogServiceContract;
use App\Enums\UserActivityAction;
use App\Enums\UserActivitySubjectType;
use App\Jobs\RecordUserActivityLogJob;
use App\Models\AntiFraudSetting;
use App\Models\Dispute;
use App\Models\Invoice;
use App\Models\Merchant;
use App\Models\OpenAiSetting;
use App\Models\Order;
use App\Models\PaymentDetail;
use App\Models\PaymentGateway;
use App\Models\Payout\Payout;
use App\Models\Setting;
use App\Models\TelegramBotSetting;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Money\Money;
use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use UnitEnum;

class UserActivityLogService implements UserActivityLogServiceContract
{
    /**
     * @var array<class-string<Model>, string>
     */
    private const SUBJECT_TYPES = [
        User::class => UserActivitySubjectType::User->value,
        Role::class => UserActivitySubjectType::Role->value,
        Merchant::class => UserActivitySubjectType::Merchant->value,
        PaymentDetail::class => UserActivitySubjectType::PaymentDetail->value,
        PaymentGateway::class => UserActivitySubjectType::PaymentGateway->value,
        Order::class => UserActivitySubjectType::Order->value,
        Payout::class => UserActivitySubjectType::Payout->value,
        Dispute::class => UserActivitySubjectType::Dispute->value,
        Wallet::class => UserActivitySubjectType::Wallet->value,
        Transaction::class => UserActivitySubjectType::Transaction->value,
        Invoice::class => UserActivitySubjectType::Invoice->value,
        Setting::class => UserActivitySubjectType::Setting->value,
        OpenAiSetting::class => UserActivitySubjectType::OpenAiSetting->value,
        AntiFraudSetting::class => UserActivitySubjectType::AntiFraudSetting->value,
        TelegramBotSetting::class => UserActivitySubjectType::TelegramBotSetting->value,
    ];

    /**
     * @var list<string>
     */
    private const SENSITIVE_FIELDS = [
        'password',
        'remember_token',
        'api_access_token',
        'apk_access_token',
        'telegram_login_token',
        'webhook_secret',
        'google2fa_secret',
        'secret',
        'token',
        'private_key',
        'signature',
        'card',
        'card_number',
        'account_number',
        'phone',
    ];

    public function recordModelEvent(Model $model, UserActivityAction $action): void
    {
        if (! $this->shouldRecord($model)) {
            return;
        }

        $changes = $this->changesFor($model, $action);

        if ($action->equals(UserActivityAction::Updated) && empty($changes)) {
            return;
        }

        $request = request();
        $actor = Auth::user();

        if (! $actor instanceof User) {
            return;
        }

        RecordUserActivityLogJob::dispatch([
            'actor_user_id' => $actor->id,
            'impersonator_user_id' => $this->impersonatorId(),
            'actor_role' => $actor->roles->pluck('name')->join(', '),
            'action' => $action->value,
            'subject_type' => self::SUBJECT_TYPES[$model::class],
            'subject_id' => $model->getKey(),
            'subject_uuid' => $this->subjectUuid($model),
            'route_name' => $request->route()?->getName(),
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 1000, ''),
            'changes' => $changes,
            'meta' => $this->metaFor($model, $request),
        ]);
    }

    public function recordRoleEvent(Model $model, UserActivityAction $action, mixed $rolesOrIds): void
    {
        if (! $this->shouldRecord($model)) {
            return;
        }

        $request = request();
        $actor = Auth::user();

        if (! $actor instanceof User) {
            return;
        }

        RecordUserActivityLogJob::dispatch([
            'actor_user_id' => $actor->id,
            'impersonator_user_id' => $this->impersonatorId(),
            'actor_role' => $actor->roles->pluck('name')->join(', '),
            'action' => $action->value,
            'subject_type' => self::SUBJECT_TYPES[$model::class],
            'subject_id' => $model->getKey(),
            'subject_uuid' => $this->subjectUuid($model),
            'route_name' => $request->route()?->getName(),
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 1000, ''),
            'changes' => [
                'roles' => [
                    $action->equals(UserActivityAction::RoleAttached) ? 'attached' : 'detached' => $this->normalizeRoleIds($rolesOrIds),
                ],
            ],
            'meta' => $this->metaFor($model, $request),
        ]);
    }

    private function shouldRecord(Model $model): bool
    {
        if (! array_key_exists($model::class, self::SUBJECT_TYPES)) {
            return false;
        }

        if (app()->runningInConsole()) {
            return false;
        }

        if (! Auth::check()) {
            return false;
        }

        $route = request()->route();

        if (! $route) {
            return false;
        }

        $middleware = collect($route->gatherMiddleware())
            ->filter(fn (mixed $middleware): bool => is_string($middleware));

        if ($middleware->contains('api')) {
            return false;
        }

        return $middleware->contains('web') || $middleware->contains('auth');
    }

    /**
     * @return array<string, mixed>
     */
    private function changesFor(Model $model, UserActivityAction $action): array
    {
        if ($action->equals(UserActivityAction::Updated)) {
            $changes = [];

            foreach ($model->getChanges() as $key => $newValue) {
                if ($key === $model::UPDATED_AT) {
                    continue;
                }

                $changes[$key] = [
                    'old' => $this->redactValue($key, $model->getOriginal($key)),
                    'new' => $this->redactValue($key, $newValue),
                ];
            }

            return $changes;
        }

        return collect($model->getAttributes())
            ->reject(fn (mixed $value, string $key): bool => $key === $model::UPDATED_AT)
            ->map(fn (mixed $value, string $key): mixed => $this->redactValue($key, $value))
            ->all();
    }

    private function redactValue(string $key, mixed $value): mixed
    {
        if ($this->isSensitiveField($key)) {
            return '[redacted]';
        }

        return $this->normalizeValue($value);
    }

    private function isSensitiveField(string $key): bool
    {
        $normalizedKey = Str::lower($key);

        return collect(self::SENSITIVE_FIELDS)
            ->contains(fn (string $field): bool => str_contains($normalizedKey, $field));
    }

    private function normalizeValue(mixed $value): mixed
    {
        if ($value instanceof Money) {
            return [
                'amount' => $value->toPrecision(),
                'currency' => $value->getCurrency()->getCode(),
            ];
        }

        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        if ($value instanceof UnitEnum) {
            return $value->name;
        }

        if (is_array($value)) {
            return collect($value)
                ->map(fn (mixed $item, string|int $key): mixed => is_string($key)
                    ? $this->redactValue($key, $item)
                    : $this->normalizeValue($item))
                ->all();
        }

        if (is_object($value) && method_exists($value, 'toArray')) {
            return $this->normalizeValue($value->toArray());
        }

        return $value;
    }

    private function subjectUuid(Model $model): ?string
    {
        $uuid = $model->getAttribute('uuid');

        return is_string($uuid) ? $uuid : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function metaFor(Model $model, Request $request): array
    {
        return [
            'model' => $model::class,
            'method' => $request->method(),
            'path' => $request->path(),
        ];
    }

    private function impersonatorId(): ?int
    {
        if (! app()->bound('impersonate')) {
            return null;
        }

        $impersonatorId = app('impersonate')->getImpersonatorId();

        return is_numeric($impersonatorId) ? (int) $impersonatorId : null;
    }

    /**
     * @return list<int|string>
     */
    private function normalizeRoleIds(mixed $rolesOrIds): array
    {
        return collect(Arr::wrap($rolesOrIds))
            ->flatten()
            ->map(function (mixed $role): int|string|null {
                if ($role instanceof Model) {
                    $key = $role->getKey();

                    return is_int($key) || is_string($key) ? $key : null;
                }

                return is_int($role) || is_string($role) ? $role : null;
            })
            ->filter(fn (int|string|null $role): bool => $role !== null)
            ->values()
            ->all();
    }
}
