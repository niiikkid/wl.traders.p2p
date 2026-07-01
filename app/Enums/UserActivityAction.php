<?php

namespace App\Enums;

use App\Traits\Enumable;

enum UserActivityAction: string
{
    use Enumable;

    case Created = 'created';
    case Updated = 'updated';
    case Deleted = 'deleted';
    case Restored = 'restored';
    case ForceDeleted = 'force_deleted';
    case RoleAttached = 'role_attached';
    case RoleDetached = 'role_detached';
}
