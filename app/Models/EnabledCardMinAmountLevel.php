<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $currency
 * @property int $min_amount
 */
class EnabledCardMinAmountLevel extends Model
{
    use HasFactory;

    protected $fillable = [
        'currency',
        'min_amount',
    ];

    protected $casts = [
        'min_amount' => 'integer',
    ];
}
