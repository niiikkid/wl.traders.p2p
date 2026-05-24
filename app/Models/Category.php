<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string $description
 * @property bool $enabled_by_default
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Collection<int, Merchant> $merchants
 * @property Collection<int, User> $traders
 */
class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'enabled_by_default',
    ];

    protected function casts(): array
    {
        return [
            'enabled_by_default' => 'boolean',
        ];
    }

    /**
     * Получить мерчантов, принадлежащих к этой категории.
     */
    public function merchants(): BelongsToMany
    {
        return $this->belongsToMany(Merchant::class);
    }

    /**
     * Трейдеры с явным выбором категории (включено/выключено в pivot).
     */
    public function traders(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'category_user')
            ->withPivot('enabled')
            ->withTimestamps();
    }
}
