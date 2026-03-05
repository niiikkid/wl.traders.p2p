<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $sender
 */
class SenderStopList extends Model
{
    protected $fillable = [
        'sender'
    ];

    public $timestamps = false;
}
