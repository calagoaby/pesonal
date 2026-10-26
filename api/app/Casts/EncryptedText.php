<?php

namespace App\Casts;

use App\Support\LedgerCipher;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Database\Eloquent\ComparesCastableAttributes;
use Illuminate\Database\Eloquent\Model;

/** @implements CastsAttributes<string|null, string|null> */
final class EncryptedText implements CastsAttributes, ComparesCastableAttributes
{
    /** @param  array<string, mixed>  $attributes */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $this->plain($value);
    }

    /**
     * An empty note is saved as null, so "no note" never becomes ciphertext.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value === null || $value === '' ? null : LedgerCipher::encrypt((string) $value);
    }

    // Every save re-runs set() with a fresh IV, so comparing ciphertext would mark an
    // unchanged value dirty and rewrite it.
    public function compare(Model $model, string $key, mixed $firstValue, mixed $secondValue): bool
    {
        return $this->plain($firstValue) === $this->plain($secondValue);
    }

    private function plain(mixed $value): ?string
    {
        return $value === null ? null : LedgerCipher::decrypt((string) $value);
    }
}
