<?php

namespace App\Casts;

use App\Support\LedgerCipher;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/** @implements CastsAttributes<string|null, string|null> */
final class EncryptedText implements CastsAttributes
{
    /** @param  array<string, mixed>  $attributes */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value === null ? null : LedgerCipher::decrypt((string) $value);
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
}
