<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $word
 */
class SmsStopWord extends Model
{
    protected $fillable = [
        'word'
    ];

    public $timestamps = false;
}
