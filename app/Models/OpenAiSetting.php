<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string|null $api_key
 * @property string|null $selected_model
 * @property array|null $available_models
 * @property Carbon|null $models_loaded_at
 */
class OpenAiSetting extends Model
{
    protected $fillable = [
        'api_key',
        'selected_model',
        'available_models',
        'models_loaded_at',
    ];

    protected $hidden = [
        'api_key',
    ];

    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'available_models' => 'array',
            'models_loaded_at' => 'datetime',
        ];
    }

    public function hasApiKey(): bool
    {
        return is_string($this->api_key) && $this->api_key !== '';
    }
}
