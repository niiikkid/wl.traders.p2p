<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property array $allowed_markets
 * @property array $allowed_categories
 * @property int $user_id
 * @property User $user
 */
class UserMeta extends Model
{
    use HasFactory;

    protected $fillable = [
        'allowed_markets',
        'allowed_categories',
    ];

    protected $casts = [
        'allowed_markets' => 'array',
        'allowed_categories' => 'array',
    ];

    public $timestamps = false;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
