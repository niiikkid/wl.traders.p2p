<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\TeamLeaderInsuranceMode;
use App\Observers\UserObserver;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Lab404\Impersonate\Models\Impersonate;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $telegram_username
 * @property string $login
 * @property string $apk_access_token
 * @property string|null $api_access_token
 * @property string|null $api_access_token_hash
 * @property string|null $webhook_secret
 * @property Collection<int, PaymentDetail> $paymentDetails
 * @property Collection<int, Order> $orders
 * @property Collection<int, Order> $teamLeaderOrders
 * @property Collection<int, Order> $agentOrders
 * @property Collection<int, Dispute> $disputes
 * @property Collection<int, SmsLog> $smsLogs
 * @property Collection<int, UserLoginHistory> $loginHistories
 * @property Collection<int, UserDevice> $devices
 * @property Collection<int, Merchant> $merchants Мерчанты (магазины), к которым имеет доступ саппорт
 * @property Wallet $wallet
 * @property UserMeta $meta
 * @property TelegramAccount|null $telegramAccount
 * @property User $merchant
 * @property bool $is_online
 * @property bool $can_set_order_amount_limits
 * @property bool $stop_traffic
 * @property bool $can_work_without_device
 * @property bool $sms_auto_close_orders_enabled
 * @property bool $payouts_enabled
 * @property bool $payout_hold_enabled
 * @property int $payout_hold_minutes
 * @property int $payout_active_payouts_limit
 * @property int|null $reserve_balance_limit
 * @property int|null $max_min_order_amount
 * @property string|null $fiat_currency
 * @property float $referral_commission_percentage
 * @property float $team_leader_split_from_service_percent
 * @property float $payout_referral_commission_percentage
 * @property float $payout_team_leader_split_from_service_percent
 * @property Carbon $traffic_enabled_at
 * @property string $google2fa_secret
 * @property bool $login_history_logging_enabled
 * @property int|null $team_leader_id
 * @property int|null $agent_id
 * @property float $agent_commission_percentage
 * @property bool $team_leader_extended_access_enabled
 * @property bool $team_leader_flexible_trader_commission_enabled
 * @property float|null $team_leader_flexible_trader_commission_min
 * @property float|null $team_leader_flexible_trader_commission_max
 * @property bool $support_can_view_deposits
 * @property bool $support_can_edit_order_amount
 * @property bool $support_can_use_manual_control_acq
 * @property bool $manual_control_acq_is_working
 * @property Carbon|null $archived_at
 * @property float|null $team_leader_individual_commission_percentage
 * @property TeamLeaderInsuranceMode $team_leader_insurance_mode
 * @property int|null $team_leader_trader_limit
 * @property int|null $team_leader_reserve_balance_limit
 * @property int|null $team_leader_reserve_stop_threshold
 * @property User|null $teamLeader
 * @property User|null $agent
 * @property Collection<int, User> $agentMerchants
 * @property Carbon $banned_at
 * @property string|null $ban_reason
 * @property int|null $banned_by_user_id
 * @property User|null $bannedBy
 * @property Carbon $created_at
 * @property Carbon $updated_At
 */
#[ObservedBy([UserObserver::class])]
class User extends Authenticatable
{
    use HasFactory, HasRoles, Impersonate, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        // Колонка email используется как login
        'email',
        'telegram_username',
        'password',
        'apk_access_token',
        'api_access_token',
        'webhook_secret',
        'is_online',
        'can_set_order_amount_limits',
        'stop_traffic',
        'can_work_without_device',
        'sms_auto_close_orders_enabled',
        'payouts_enabled',
        'payout_hold_enabled',
        'payout_hold_minutes',
        'payout_active_payouts_limit',
        'reserve_balance_limit',
        'max_min_order_amount',
        'fiat_currency',
        'referral_commission_percentage',
        'team_leader_split_from_service_percent',
        'payout_referral_commission_percentage',
        'payout_team_leader_split_from_service_percent',
        'traffic_enabled_at',
        'google2fa_secret',
        'login_history_logging_enabled',
        'team_leader_id',
        'team_leader_extended_access_enabled',
        'team_leader_flexible_trader_commission_enabled',
        'team_leader_flexible_trader_commission_min',
        'team_leader_flexible_trader_commission_max',
        'support_can_view_deposits',
        'support_can_edit_order_amount',
        'support_can_use_manual_control_acq',
        'manual_control_acq_is_working',
        'team_leader_individual_commission_percentage',
        'team_leader_insurance_mode',
        'team_leader_trader_limit',
        'team_leader_reserve_balance_limit',
        'team_leader_reserve_stop_threshold',
        'banned_at',
        'ban_reason',
        'banned_by_user_id',
        'archived_at',
        'merchant_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'google2fa_secret',
        'apk_access_token',
        'api_access_token',
        'api_access_token_hash',
        'webhook_secret',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'banned_at' => 'datetime',
            'archived_at' => 'datetime',
            'traffic_enabled_at' => 'datetime',
            'can_work_without_device' => 'boolean',
            'sms_auto_close_orders_enabled' => 'boolean',
            'payouts_enabled' => 'boolean',
            'payout_hold_enabled' => 'boolean',
            'payout_active_payouts_limit' => 'integer',
            'referral_commission_percentage' => 'float',
            'team_leader_split_from_service_percent' => 'float',
            'payout_referral_commission_percentage' => 'float',
            'payout_team_leader_split_from_service_percent' => 'float',
            'agent_commission_percentage' => 'float',
            'team_leader_extended_access_enabled' => 'boolean',
            'team_leader_flexible_trader_commission_enabled' => 'boolean',
            'team_leader_flexible_trader_commission_min' => 'float',
            'team_leader_flexible_trader_commission_max' => 'float',
            'support_can_view_deposits' => 'boolean',
            'support_can_edit_order_amount' => 'boolean',
            'support_can_use_manual_control_acq' => 'boolean',
            'manual_control_acq_is_working' => 'boolean',
            'team_leader_individual_commission_percentage' => 'float',
            'team_leader_insurance_mode' => TeamLeaderInsuranceMode::class,
            'team_leader_trader_limit' => 'integer',
            'team_leader_reserve_balance_limit' => 'integer',
            'team_leader_reserve_stop_threshold' => 'integer',
            'login_history_logging_enabled' => 'boolean',
        ];
    }

    public static function generateApiAccessToken(): string
    {
        do {
            $token = strtolower(Str::random(64));
        } while (static::query()->where('api_access_token_hash', static::hashApiAccessToken($token))->exists());

        return $token;
    }

    public static function generateWebhookSecret(): string
    {
        return 'whsec_'.Str::random(64);
    }

    public static function hashApiAccessToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public function rotateApiAccessToken(): string
    {
        $token = static::generateApiAccessToken();

        $this->forceFill([
            'api_access_token' => $token,
        ])->save();

        return $token;
    }

    public function rotateWebhookSecret(): string
    {
        $secret = static::generateWebhookSecret();

        $this->forceFill([
            'webhook_secret' => $secret,
        ])->save();

        return $secret;
    }

    public function signWebhookPayload(string $payload): ?string
    {
        if (! $this->webhook_secret) {
            return null;
        }

        return hash_hmac('sha256', $payload, $this->webhook_secret);
    }

    public function effectiveMaxMinOrderAmount(): ?int
    {
        if ($this->max_min_order_amount === null || $this->max_min_order_amount <= 0) {
            return null;
        }

        return $this->max_min_order_amount;
    }

    public function usesTeamLeaderSharedReserve(): bool
    {
        if ($this->team_leader_id === null) {
            return false;
        }

        $teamLeader = $this->relationLoaded('teamLeader')
            ? $this->teamLeader
            : $this->teamLeader()->first(['id', 'team_leader_insurance_mode']);

        return $teamLeader?->team_leader_insurance_mode->usesSharedReserve() ?? false;
    }

    public function connectedTraderCount(): int
    {
        return $this->referrals()->role('Trader')->count();
    }

    public function remainingTeamLeaderTraderSlots(): ?int
    {
        if ($this->team_leader_trader_limit === null) {
            return null;
        }

        return max(0, $this->team_leader_trader_limit - $this->connectedTraderCount());
    }

    protected function google2faSecret(): Attribute
    {
        return new Attribute(
            get: fn ($value) => $value ? decrypt($value) : null,
            set: fn ($value) => $value ? encrypt($value) : null,
        );
    }

    protected function apiAccessToken(): Attribute
    {
        return new Attribute(
            get: fn ($value) => $value ? decrypt($value) : null,
            set: fn ($value) => $value ? [
                'api_access_token' => encrypt($value),
                'api_access_token_hash' => static::hashApiAccessToken($value),
            ] : [
                'api_access_token' => null,
                'api_access_token_hash' => null,
            ],
        );
    }

    protected function webhookSecret(): Attribute
    {
        return new Attribute(
            get: fn ($value) => $value ? decrypt($value) : null,
            set: fn ($value) => $value ? encrypt($value) : null,
        );
    }

    public function canImpersonate()
    {
        return $this->hasRole('Super Admin');
    }

    public function canBeImpersonated()
    {
        return ! $this->hasRole('Super Admin');
    }

    public function paymentDetails(): HasMany
    {
        return $this->hasMany(PaymentDetail::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'trader_id');
    }

    public function teamLeaderOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'team_leader_id');
    }

    public function agentOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'agent_id');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function disputes(): HasMany
    {
        return $this->hasMany(Dispute::class, 'trader_id');
    }

    public function smsLogs(): HasMany
    {
        return $this->hasMany(SmsLog::class);
    }

    public function devices(): HasMany
    {
        return $this->hasMany(UserDevice::class);
    }

    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class);
    }

    public function meta(): HasOne
    {
        return $this->hasOne(UserMeta::class);
    }

    public function telegramAccount(): HasOne
    {
        return $this->hasOne(TelegramAccount::class);
    }

    public function telegramTeamChats(): BelongsToMany
    {
        return $this->belongsToMany(TelegramChat::class, 'telegram_chat_traders', 'trader_id', 'telegram_chat_id')
            ->withPivot('telegram_username')
            ->withTimestamps();
    }

    public function teamLeader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'team_leader_id');
    }

    public function bannedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'banned_by_user_id');
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(User::class, 'team_leader_id');
    }

    public function agentMerchants(): HasMany
    {
        return $this->hasMany(User::class, 'agent_id');
    }

    /**
     * Get the login histories for the user.
     */
    public function loginHistories(): HasMany
    {
        return $this->hasMany(UserLoginHistory::class);
    }

    /**
     * Получить мерчанта, к которому привязан саппорт
     */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'merchant_id');
    }

    /**
     * Получить саппортов мерчанта
     */
    public function supports(): HasMany
    {
        return $this->hasMany(User::class, 'merchant_id');
    }

    /**
     * Получить мерчанты (магазины), к которым имеет доступ саппорт
     */
    public function merchants(): BelongsToMany
    {
        return $this->belongsToMany(Merchant::class, 'merchant_supports', 'support_id', 'merchant_id')
            ->withTimestamps();
    }

    public function onlinePeriods(): HasMany
    {
        return $this->hasMany(UserOnlinePeriod::class);
    }
}
