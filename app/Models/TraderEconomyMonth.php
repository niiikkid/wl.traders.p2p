<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $user_id
 * @property int $year
 * @property int $month
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property User $user
 * @property Collection<int, TraderEconomyDay> $days
 */
class TraderEconomyMonth extends Model
{
    protected $table = 'trader_economy_months';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'year',
        'month',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'month' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function days(): HasMany
    {
        return $this->hasMany(TraderEconomyDay::class);
    }
}
