<?php

namespace App\Listeners;

use App\Enums\UserActivityAction;
use Spatie\Permission\Events\RoleAttached;

class RecordRoleAttachedActivity
{
    public function handle(RoleAttached $event): void
    {
        services()->userActivityLog()->recordRoleEvent(
            $event->model,
            UserActivityAction::RoleAttached,
            $event->rolesOrIds,
        );
    }
}
