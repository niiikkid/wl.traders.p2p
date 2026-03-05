<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property Carbon $date
 * @property bool $is_successful
 * @property string|null $currency
 * @property int $count
 * @property float $sum_amount
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class MerchantApiStatistic extends Model
{
    use HasFactory;

    protected $fillable = [
        'date',
        'is_successful',
        'currency',
        'count',
        'sum_amount',
    ];

    protected $casts = [
        'date' => 'date',
        'is_successful' => 'boolean',
        'count' => 'integer',
        'sum_amount' => 'decimal:8',
    ];
}
