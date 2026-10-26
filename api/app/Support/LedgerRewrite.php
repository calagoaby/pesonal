<?php

namespace App\Support;

use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Rewrites the encrypted ledger columns in place, shared by the encryption migration and
 * pesonal:encrypt-ledger. DB::table on purpose: no ownership scope (nobody is signed in),
 * no casts, soft-deleted rows included, and updated_at is left alone (the app reads it as
 * "paused since" and "last changed").
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

    /**
     * Encrypts every plain value; one already encrypted is left byte for byte. An empty
     * note becomes null, as the cast saves it. Returns the rows changed, per table.
     *
     * @return array<string, int>
     */
    public static function encrypt(): array
    {
        return self::everyTable(fn (string $column, string $value) => match (true) {
            $column === 'note' && $value === '' => null,
            LedgerCipher::isEncrypted($value) => $value,
            default => LedgerCipher::encrypt($value),
        });
    }

    /**
     * Back to plain text. An amount must decrypt to an integer, so nothing else reaches the
     * integer column. Returns the rows changed, per table.
     *
     * @return array<string, int>
     */
    public static function decrypt(): array
    {
        return self::everyTable(fn (string $column, string $value) => $column === 'amount'
            ? (string) LedgerCipher::decryptAmount($value)
            : LedgerCipher::decrypt($value));
    }

    /**
     * Recomputes name_index from the decrypted name, so a stale one (a rename by an older
     * container, a rotated APP_KEY) is repaired. A fingerprint another category of the same
     * account already holds can't be stored under the unique index: that row keeps its old
     * one and is reported, so a duplicate name never crashes the caller.
     *
     * @return array{changed: int, collisions: list<array{id: int, other_id: int, user_id: int}>}
     */
    public static function fingerprint(): array
    {
        $changed = 0;
        $collisions = [];

        DB::table('categories')->select(['id', 'user_id', 'name', 'name_index'])->chunkById(self::CHUNK, function ($rows) use (&$changed, &$collisions) {
            foreach ($rows as $row) {
                $index = LedgerCipher::fingerprint((int) $row->user_id, LedgerCipher::decrypt((string) $row->name));
                if ($index === $row->name_index) {
                    continue;
                }

                try {
                    // A transaction (a savepoint when nested) so a refused row doesn't abort
                    // an enclosing Postgres transaction.
                    DB::transaction(fn () => DB::table('categories')->where('id', $row->id)->update(['name_index' => $index]));
                    $changed++;
                } catch (UniqueConstraintViolationException) {
                    $collisions[] = [
                        'id' => (int) $row->id,
                        'other_id' => (int) DB::table('categories')->where('user_id', $row->user_id)->where('name_index', $index)->value('id'),
                        'user_id' => (int) $row->user_id,
                    ];
                }
            }
        });

        return ['changed' => $changed, 'collisions' => $collisions];
    }

    /**
     * @param  Closure(string, string): ?string  $transform  column, stored value => new value
     * @return array<string, int>
     */
    private static function everyTable(Closure $transform): array
    {
        $changed = [];
        foreach (self::COLUMNS as $table => $columns) {
            $changed[$table] = self::rewrite($table, $columns, $transform);
        }

        return $changed;
    }

    /**
     * @param  list<string>  $columns
     * @param  Closure(string, string): ?string  $transform
     */
    private static function rewrite(string $table, array $columns, Closure $transform): int
    {
        $changed = 0;

        DB::table($table)->select(['id', ...$columns])->chunkById(self::CHUNK, function ($rows) use ($table, $columns, $transform, &$changed) {
            foreach ($rows as $row) {
                $changes = [];
                foreach ($columns as $column) {
                    if ($row->{$column} === null) {
                        continue;
                    }
                    $value = (string) $row->{$column};
                    $new = $transform($column, $value);
                    if ($new !== $value) {
                        $changes[$column] = $new;
                    }
                }

                if ($changes !== []) {
                    DB::table($table)->where('id', $row->id)->update($changes);
                    $changed++;
                }
            }
        });

        return $changed;
    }
}
