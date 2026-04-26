<?php

declare(strict_types=1);

namespace App\Casts;

use App\Models\ValueObjects\CascadeManualControl;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * @implements CastsAttributes<CascadeManualControl|null, CascadeManualControl|array<string, mixed>|null>
 */
class CascadeManualControlCast implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?CascadeManualControl
    {
        if ($value === null) {
            return null;
        }

        $data = json_decode((string) $value, true);

        return CascadeManualControl::fromArray(is_array($data) ? $data : null);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof CascadeManualControl) {
            return json_encode($value->toArray());
        }

        if (is_array($value)) {
            return json_encode($value);
        }

        return null;
    }
}
