<?php

namespace App\Casts;

use App\Enums\DetailType;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class DetailTypesCast implements CastsAttributes
{
    /**
     * Cast the given value.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        $value = json_decode($value, true);

        $detailTypes = collect();
        foreach ($value as $detailType) {
            $detailTypes->push(DetailType::from($detailType));
        }

        return $detailTypes;
    }

    /**
     * Prepare the given value for storage.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        $detailTypes = [];
        foreach ($value as $detailType) {
            if ($detailType instanceof DetailType) {
                $detailTypes[] = $detailType->value;
            } else {
                $detailTypes[] = $detailType;
            }
        }

        return json_encode($detailTypes);
    }
}
