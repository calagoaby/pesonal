<?php

namespace App\Casts;

use App\Support\LedgerCipher;
use App\Support\Money;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Database\Eloquent\ComparesCastableAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use UnexpectedValueException;

/** @implements CastsAttributes<Money, Money|int> */
final class MoneyCast implements CastsAttributes, ComparesCastableAttributes
{
    private bool $encrypts;

    // MoneyCast::class.':plain' stores a bare int: the columns stay bigint until the
    // widening migration, which is when the models drop ':plain'.
    public function __construct(?string $storage = null)
    {
        if ($storage !== null && $storage !== 'plain') {
            throw new InvalidArgumentException("Unknown MoneyCast option '{$storage}'.");
        }

        $this->encrypts = $storage === null;
    }

    /** @param  array<string, mixed>  $attributes */
    public function get(Model $model, string $key, mixed $value, array $attributes): Money
    {
        return Money::fromCentavos($this->centavos($key, $value));
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

    // Every save re-runs set() with a fresh IV, so comparing ciphertext would mark an
    // unchanged amount dirty and rewrite it.
    public function compare(Model $model, string $key, mixed $firstValue, mixed $secondValue): bool
    {
        return $this->centavos($key, $firstValue) === $this->centavos($key, $secondValue);
    }

    private function centavos(string $key, mixed $value): int
    {
        if ($value === null) {
            throw new UnexpectedValueException("The {$key} is missing.");
        }

        return LedgerCipher::decryptAmount(is_int($value) ? $value : (string) $value);
    }
}
