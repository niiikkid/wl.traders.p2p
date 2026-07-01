<?php

namespace App\Listeners;

use App\Enums\UserActivityAction;
use Spatie\Permission\Events\RoleDetached;

class RecordRoleDetachedActivity
{
    public function handle(RoleDetached $event): void
    {
        services()->userActivityLog()->recordRoleEvent(
            $event->model,
            UserActivityAction::RoleDetached,
            $event->rolesOrIds,
        );
    }
}
