<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property array $allowed_markets
 * @property array $allowed_categories
 * @property Carbon|null $news_last_read_at
 * @property bool $manual_control_acq_new_offer_sound_enabled
 * @property string|null $manual_control_acq_new_offer_sound_track
 * @property bool $manual_control_acq_confirm_code_sound_enabled
 * @property string|null $manual_control_acq_confirm_code_sound_track
 * @property bool $notification_sound_order_enabled
 * @property string|null $notification_sound_order_track
 * @property bool $notification_sound_dispute_enabled
 * @property string|null $notification_sound_dispute_track
 * @property bool $notification_sound_message_enabled
 * @property string|null $notification_sound_message_track
 * @property int $user_id
 * @property User $user
 */
class UserMeta extends Model
{
    use HasFactory;

    protected $fillable = [
        'allowed_markets',
        'allowed_categories',
        'news_last_read_at',
        'manual_control_acq_new_offer_sound_enabled',
        'manual_control_acq_new_offer_sound_track',
        'manual_control_acq_confirm_code_sound_enabled',
        'manual_control_acq_confirm_code_sound_track',
        'notification_sound_order_enabled',
        'notification_sound_order_track',
        'notification_sound_dispute_enabled',
        'notification_sound_dispute_track',
        'notification_sound_message_enabled',
        'notification_sound_message_track',
    ];

    protected $casts = [
        'allowed_markets' => 'array',
        'allowed_categories' => 'array',
        'news_last_read_at' => 'datetime',
        'manual_control_acq_new_offer_sound_enabled' => 'boolean',
        'manual_control_acq_confirm_code_sound_enabled' => 'boolean',
        'notification_sound_order_enabled' => 'boolean',
        'notification_sound_dispute_enabled' => 'boolean',
        'notification_sound_message_enabled' => 'boolean',
    ];

    public $timestamps = false;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
