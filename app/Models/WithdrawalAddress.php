<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WithdrawalAddress extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'address',
        'address_hash',
    ];

    protected $casts = [
        'address' => 'encrypted',
    ];

    public static function hashAddress(string $address): string
    {
        return hash('sha256', strtolower(trim($address)));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    protected function maskedAddress(): Attribute
    {
        return Attribute::get(function (): string {
            $address = $this->address;

            return substr($address, 0, 6).'...'.substr($address, -6);
        });
    }
}
