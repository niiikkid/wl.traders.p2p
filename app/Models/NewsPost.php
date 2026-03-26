<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NewsPost extends Model
{
    use HasFactory;

    protected $fillable = [
        'author_id',
        'cover_image_path',
        'title',
        'is_visible_for_all',
        'visible_role_names',
        'content_json',
        'content_html',
    ];

    protected $casts = [
        'is_visible_for_all' => 'boolean',
        'visible_role_names' => 'array',
        'content_json' => 'array',
    ];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * @param array<int, string> $roleNames
     */
    public function scopeVisibleForRoles(Builder $query, array $roleNames): Builder
    {
        return $query->where(function (Builder $query) use ($roleNames) {
            $query->where('is_visible_for_all', true);

            if (empty($roleNames)) {
                return;
            }

            $query->orWhere(function (Builder $query) use ($roleNames) {
                $query->where('is_visible_for_all', false)
                    ->where(function (Builder $query) use ($roleNames) {
                        foreach ($roleNames as $roleName) {
                            $query->orWhereJsonContains('visible_role_names', $roleName);
                        }
                    });
            });
        });
    }
}
