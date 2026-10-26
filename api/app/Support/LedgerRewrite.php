<?php

namespace App\Support;

use Closure;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Rewrites the encrypted ledger columns in place, shared by the encryption migration and
 * pesonal:encrypt-ledger. DB::table on purpose: no ownership scope (nobody is signed in),
 * no casts, soft-deleted rows included, and updated_at is left alone (the app reads it as
 * "paused since" and "last changed").
 *
 * The migration 2026_10_26_000001 depends on this behaviour (it passes its own column
 * list, but encrypt, decrypt and fingerprint are these): a change here changes what that
 * migration does on a fresh database.
 */
final class LedgerRewrite
{
    // Each table's encrypted columns. amount is never null; a null note stays null.
    public const COLUMNS = [
        'expenses' => ['amount', 'note'],
        'recurring' => ['amount', 'note'],
        'categories' => ['name'],
    ];

    private const CHUNK = 500;

    // How many existing ciphertexts the key check tries: one corrupt row alone mustn't
    // read as a wrong key.
    private const KEY_SAMPLES = 3;

    /**
     * True when the current APP_KEY (or one of APP_PREVIOUS_KEYS) decrypts the existing
     * ledger: any of a few category names, else amounts. No ciphertext at all is a pass.
     */
    public static function keyReadsLedger(): bool
    {
        foreach (['categories' => 'name', 'expenses' => 'amount', 'recurring' => 'amount'] as $table => $column) {
            // base64 of '{"' is 'eyJ': a cheap prefilter; isEncrypted decides.
            $samples = DB::table($table)->where($column, 'like', 'eyJ%')->orderBy('id')->limit(50)->pluck($column)
                ->map(fn ($value) => (string) $value)
                ->filter(fn (string $value) => LedgerCipher::isEncrypted($value))
                ->take(self::KEY_SAMPLES);

            if ($samples->isEmpty()) {
                continue;
            }

            foreach ($samples as $sample) {
                try {
                    LedgerCipher::decrypt($sample);

                    return true;
                } catch (DecryptException) {
                    // Try the next one.
                }
            }

            return false;
        }

        return true;
    }

    /**
     * Encrypts every plain value; one already encrypted is left byte for byte (nothing is
     * decrypted here). An empty note becomes null, as the cast saves it. Returns the rows
     * changed, per table.
     *
     * @param  array<string, list<string>>  $columns  table => encrypted columns
     * @return array<string, int>
     */
    public static function encrypt(array $columns = self::COLUMNS): array
    {
        return self::everyTable($columns, fn (string $column, string $value) => match (true) {
            $column === 'note' && $value === '' => null,
            LedgerCipher::isEncrypted($value) => $value,
            default => LedgerCipher::encrypt($value),
        });
    }

    /**
     * Back to plain text. An amount must decrypt to an integer, so nothing else reaches the
     * integer column; any failure throws. Returns the rows changed, per table.
     *
     * @param  array<string, list<string>>  $columns  table => encrypted columns
     * @return array<string, int>
     */
    public static function decrypt(array $columns = self::COLUMNS): array
    {
        return self::everyTable($columns, fn (string $column, string $value) => $column === 'amount'
            ? (string) LedgerCipher::decryptAmount($value)
            : LedgerCipher::decrypt($value));
    }

    /**
     * Recomputes name_index from the decrypted name, so a stale one (a rename by an older
     * container, a rotated APP_KEY) is repaired. A fingerprint another category of the same
     * account holds can't be stored under the unique index; those rows are retried once at
     * the end (the holder may have moved on, or two stale ones were swapped). Rows that
     * still collide keep their old fingerprint and are reported, as are names that don't
     * decrypt when $skipUnreadable: neither crashes the caller.
     *
     * @return array{changed: int, collisions: list<array{id: int, other_id: int, user_id: int}>, unreadable: list<array{id: int, user_id: int}>}
     */
    public static function fingerprint(bool $skipUnreadable = false): array
    {
        $changed = 0;
        $pending = [];
        $unreadable = [];

        DB::table('categories')->select(['id', 'user_id', 'name', 'name_index'])->chunkById(self::CHUNK, function ($rows) use (&$changed, &$pending, &$unreadable, $skipUnreadable) {
            foreach ($rows as $row) {
                try {
                    $index = LedgerCipher::fingerprint((int) $row->user_id, LedgerCipher::decrypt((string) $row->name));
                } catch (DecryptException $e) {
                    if (! $skipUnreadable) {
                        throw $e;
                    }
                    $unreadable[] = ['id' => (int) $row->id, 'user_id' => (int) $row->user_id];

                    continue;
                }

                if ($index === $row->name_index) {
                    continue;
                }

                $result = self::storeIndex($row, $index);
                if ($result === true) {
                    $changed++;
                } elseif ($result === null) {
                    $pending[] = [$row, $index];
                }
            }
        });

        // The retry. Each collided row first parks on a placeholder that can't clash (the
        // real ones are 64 hex characters), which frees its old value for a swapped partner.
        foreach ($pending as [$row, $index]) {
            if (self::storeIndex($row, 'pending:'.$row->id) === true) {
                $row->name_index = 'pending:'.$row->id;
            }
        }

        $collisions = [];
        foreach ($pending as [$row, $index]) {
            $result = self::storeIndex($row, $index);
            if ($result === true) {
                $changed++;

                continue;
            }

            if ($result === null) {
                // Back to its old fingerprint if that is still free; else the placeholder stays.
                self::storeIndex($row, $row->original_index);
                $collisions[] = [
                    'id' => (int) $row->id,
                    'other_id' => (int) DB::table('categories')->where('user_id', $row->user_id)->where('name_index', $index)->value('id'),
                    'user_id' => (int) $row->user_id,
                ];
            }
        }

        return ['changed' => $changed, 'collisions' => $collisions, 'unreadable' => $unreadable];
    }

    /**
     * A compare-and-swap: written only while name and name_index are what was read, so a
     * rename landing in between is never overwritten (the next run fingerprints it).
     * True when stored, false when the row changed underneath, null on a collision.
     */
    private static function storeIndex(object $row, string $index): ?bool
    {
        $row->original_index ??= $row->name_index;

        try {
            // A transaction (a savepoint when nested) so a refused row doesn't abort an
            // enclosing Postgres transaction.
            $updated = DB::transaction(fn () => DB::table('categories')
                ->where('id', $row->id)
                ->where('name', $row->name)
                ->where('name_index', $row->name_index)
                ->update(['name_index' => $index]));
        } catch (UniqueConstraintViolationException) {
            return null;
        }

        if ($updated > 0) {
            $row->name_index = $index;
        }

        return $updated > 0;
    }

    /**
     * @param  array<string, list<string>>  $columns
     * @param  Closure(string, string): ?string  $transform  column, stored value => new value
     * @return array<string, int>
     */
    private static function everyTable(array $columns, Closure $transform): array
    {
        $changed = [];
        foreach ($columns as $table => $tableColumns) {
            $changed[$table] = self::rewrite($table, $tableColumns, $transform);
        }

        return $changed;
    }

    /**
     * Each write is a compare-and-swap on the values read: an edit by an older container
     * landing between the read and the write wins, and is encrypted on the next run.
     * Counts only rows actually written.
     *
     * @param  list<string>  $columns
     * @param  Closure(string, string): ?string  $transform
     */
    private static function rewrite(string $table, array $columns, Closure $transform): int
    {
        $changed = 0;

        DB::table($table)->select(['id', ...$columns])->chunkById(self::CHUNK, function ($rows) use ($table, $columns, $transform, &$changed) {
            foreach ($rows as $row) {
                $changes = [];
                $query = DB::table($table)->where('id', $row->id);
                foreach ($columns as $column) {
                    if ($row->{$column} === null) {
                        continue;
                    }
                    $value = (string) $row->{$column};
                    $new = $transform($column, $value);
                    if ($new !== $value) {
                        $changes[$column] = $new;
                        $query->where($column, $value);
                    }
                }

                if ($changes !== []) {
                    $changed += $query->update($changes);
                }
            }
        });

        return $changed;
    }
}
