<?php

use App\Enums\NotificationEvent;
use App\Enums\NotificationMessageScope;
use App\Services\Money\Currency;
use App\Services\Money\Money;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $tableNames = config('permission.table_names');
        $rolesTable = $tableNames['roles'] ?? 'roles';
        $modelHasRolesTable = $tableNames['model_has_roles'] ?? 'model_has_roles';
        $usersTable = 'users';
        $notificationRulesTable = 'notification_rules';
        $userModelType = app(\App\Models\User::class)->getMorphClass();

        $targetUsersQuery = DB::table($usersTable)
            ->select("{$usersTable}.id")
            ->join($modelHasRolesTable, function ($join) use ($usersTable, $modelHasRolesTable, $userModelType) {
                $join->on("{$modelHasRolesTable}.model_id", '=', "{$usersTable}.id")
                    ->where("{$modelHasRolesTable}.model_type", '=', $userModelType);
            })
            ->join($rolesTable, "{$rolesTable}.id", '=', "{$modelHasRolesTable}.role_id")
            ->whereIn("{$rolesTable}.name", ['Super Admin', 'Trader'])
            ->distinct();

        DB::table($notificationRulesTable)
            ->whereIn('user_id', $targetUsersQuery)
            ->delete();

        $adminUserIds = DB::table($usersTable)
            ->select("{$usersTable}.id")
            ->join($modelHasRolesTable, function ($join) use ($usersTable, $modelHasRolesTable, $userModelType) {
                $join->on("{$modelHasRolesTable}.model_id", '=', "{$usersTable}.id")
                    ->where("{$modelHasRolesTable}.model_type", '=', $userModelType);
            })
            ->join($rolesTable, "{$rolesTable}.id", '=', "{$modelHasRolesTable}.role_id")
            ->where("{$rolesTable}.name", 'Super Admin')
            ->distinct()
            ->pluck("{$usersTable}.id");

        $traderUserIds = DB::table($usersTable)
            ->select("{$usersTable}.id")
            ->join($modelHasRolesTable, function ($join) use ($usersTable, $modelHasRolesTable, $userModelType) {
                $join->on("{$modelHasRolesTable}.model_id", '=', "{$usersTable}.id")
                    ->where("{$modelHasRolesTable}.model_type", '=', $userModelType);
            })
            ->join($rolesTable, "{$rolesTable}.id", '=', "{$modelHasRolesTable}.role_id")
            ->where("{$rolesTable}.name", 'Trader')
            ->distinct()
            ->pluck("{$usersTable}.id");

        if ($adminUserIds->isEmpty() && $traderUserIds->isEmpty()) {
            return;
        }

        $now = now();
        $trustBalanceThreshold = Money::fromPrecision('500', Currency::USDT()->getCode())->toUnits();
        $rows = [];

        foreach ($adminUserIds as $userId) {
            $rows[] = [
                'user_id' => $userId,
                'event' => NotificationEvent::WITHDRAWAL_REQUESTED->value,
                'currency' => null,
                'statuses' => null,
                'message_scope' => null,
                'min_amount_minor' => null,
                'enabled' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach ($traderUserIds as $userId) {
            $rows[] = [
                'user_id' => $userId,
                'event' => NotificationEvent::ORDER_ASSIGNED->value,
                'currency' => null,
                'statuses' => null,
                'message_scope' => null,
                'min_amount_minor' => null,
                'enabled' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $rows[] = [
                'user_id' => $userId,
                'event' => NotificationEvent::DISPUTE_OPENED->value,
                'currency' => null,
                'statuses' => null,
                'message_scope' => null,
                'min_amount_minor' => null,
                'enabled' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $rows[] = [
                'user_id' => $userId,
                'event' => NotificationEvent::TRUST_BALANCE_LOW->value,
                'currency' => Currency::USDT()->getCode(),
                'statuses' => null,
                'message_scope' => null,
                'min_amount_minor' => $trustBalanceThreshold,
                'enabled' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $rows[] = [
                'user_id' => $userId,
                'event' => NotificationEvent::MESSAGE_RECEIVED->value,
                'currency' => null,
                'statuses' => null,
                'message_scope' => NotificationMessageScope::ALL->value,
                'min_amount_minor' => null,
                'enabled' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows !== []) {
            DB::table($notificationRulesTable)->insert($rows);
        }
    }

    public function down(): void
    {
        // Intentionally empty: previous custom user rules cannot be restored automatically.
    }
};
