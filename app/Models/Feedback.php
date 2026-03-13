<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property int $user_id
 * @property string $content
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property User $author
 */
class Feedback extends Model
{
    use HasFactory;

    protected $table = 'feedbacks';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'content',
    ];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function favoritedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'feedback_favorites')
            ->withTimestamps();
    }

    public function hiddenBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'feedback_hides')
            ->withTimestamps();
    }
}
