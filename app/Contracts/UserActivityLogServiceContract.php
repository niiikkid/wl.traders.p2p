<?php

namespace App\Contracts;

use App\Enums\UserActivityAction;
use Illuminate\Database\Eloquent\Model;

interface UserActivityLogServiceContract
{
    public function recordModelEvent(Model $model, UserActivityAction $action): void;

    public function recordRoleEvent(Model $model, UserActivityAction $action, mixed $rolesOrIds): void;
}
