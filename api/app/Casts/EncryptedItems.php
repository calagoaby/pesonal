<?php

namespace App\Casts;

use App\Support\LedgerCipher;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Database\Eloquent\ComparesCastableAttributes;
use Illuminate\Database\Eloquent\Model;
use JsonException;
use UnexpectedValueException;

/** @implements CastsAttributes<list<array{name: string, amount: int}>, list<array{name: string, amount: int}>|null> */
final class EncryptedItems implements CastsAttributes, ComparesCastableAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     * @return list<array{name: string, amount: int}>
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): array
    {
        return $this->items($value);
    }

    /**
     * An empty list (or null) is saved as null, so "no items" never becomes ciphertext.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if (! is_array($value) || $value === []) {
            return null;
        }

        return LedgerCipher::encrypt(json_encode($value, JSON_THROW_ON_ERROR));
    }

    // Every save re-runs set() with a fresh IV, so comparing ciphertext would mark an
    // unchanged list dirty and rewrite it.
    public function compare(Model $model, string $key, mixed $firstValue, mixed $secondValue): bool
    {
        return $this->items($firstValue) === $this->items($secondValue);
    }

    /** @return list<array{name: string, amount: int}> */
    private function items(mixed $value): array
    {
        if ($value === null) {
            return [];
        }

        try {
            $decoded = json_decode(LedgerCipher::decrypt((string) $value), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new UnexpectedValueException('The stored items are not valid JSON.', 0, $e);
        }

        if (! is_array($decoded) || ! array_is_list($decoded)) {
            throw new UnexpectedValueException('The stored items are not a list.');
        }

        $items = [];

        foreach ($decoded as $item) {
            if (! is_array($item) || ! is_string($item['name'] ?? null) || ! is_int($item['amount'] ?? null)) {
                throw new UnexpectedValueException('A stored item is not a name and an amount.');
            }

            $items[] = ['name' => $item['name'], 'amount' => $item['amount']];
        }

        return $items;
    }
}
