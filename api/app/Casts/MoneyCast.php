<?php

namespace App\Casts;

use App\Support\Money;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/** @implements CastsAttributes<Money, Money|int> */
final class MoneyCast implements CastsAttributes
{
    /** @param  array<string, mixed>  $attributes */
    public function get(Model $model, string $key, mixed $value, array $attributes): Money
    {
        return Money::fromCentavos((int) $value);
    }

    /**
     * Accepts either a Money or a raw int: controllers and factories assign a
     * bare centavos int. amount is non-nullable, so no null handling is needed.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): int
    {
        return $value instanceof Money ? $value->centavos() : (int) $value;
    }
}
