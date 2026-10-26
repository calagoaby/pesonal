<?php

namespace App\Casts;

use App\Support\LedgerCipher;
use App\Support\Money;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/** @implements CastsAttributes<Money, Money|int> */
final class MoneyCast implements CastsAttributes
{
    private bool $encrypts;

    // MoneyCast::class.':plain' stores a bare int: the columns stay bigint until the
    // widening migration, which is when the models drop ':plain'.
    public function __construct(?string $storage = null)
    {
        $this->encrypts = $storage !== 'plain';
    }

    /** @param  array<string, mixed>  $attributes */
    public function get(Model $model, string $key, mixed $value, array $attributes): Money
    {
        return Money::fromCentavos(LedgerCipher::decryptAmount(is_int($value) ? $value : (string) $value));
    }

    /**
     * Accepts either a Money or a raw int: controllers and factories assign a
     * bare centavos int. amount is non-nullable, so no null handling is needed.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): int|string
    {
        $centavos = $value instanceof Money ? $value->centavos() : (int) $value;

        return $this->encrypts ? LedgerCipher::encrypt((string) $centavos) : $centavos;
    }
}
