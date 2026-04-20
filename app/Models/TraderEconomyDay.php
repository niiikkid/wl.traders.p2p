<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $trader_economy_month_id
 * @property int $day
 * @property string|null $rate
 * @property string|null $start_balance
 * @property string|null $card_uah
 * @property string|null $end_balance
 * @property string|null $exchange_balance
 * @property string|null $circles
 * @property string|null $arbitrage_usd
 * @property string|null $expense_uah
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property TraderEconomyMonth $economyMonth
 */
class TraderEconomyDay extends Model
{
    protected $table = 'trader_economy_days';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'trader_economy_month_id',
        'day',
        'rate',
        'start_balance',
        'card_uah',
        'end_balance',
        'exchange_balance',
        'circles',
        'arbitrage_usd',
        'expense_uah',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'day' => 'integer',
        ];
    }

    public function economyMonth(): BelongsTo
    {
        return $this->belongsTo(TraderEconomyMonth::class, 'trader_economy_month_id');
    }
}
