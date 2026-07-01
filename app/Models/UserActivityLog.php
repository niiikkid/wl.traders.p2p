<?php

namespace App\Models;

use App\Enums\UserActivityAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserActivityLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'actor_user_id',
        'impersonator_user_id',
        'actor_role',
        'action',
        'subject_type',
        'subject_id',
        'subject_uuid',
        'route_name',
        'ip_address',
        'user_agent',
        'changes',
        'meta',
    ];

    protected $casts = [
        'action' => UserActivityAction::class,
        'changes' => 'array',
        'meta' => 'array',
        'created_at' => 'datetime',
    ];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function impersonator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'impersonator_user_id');
    }
}
