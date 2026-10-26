<?php

use App\Support\LedgerCipher;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Amounts, notes and category names become ciphertext (APP_KEY), in place. Ciphertext is
// a long base64 string, so the bigint amounts and the varchar(255) names widen to text,
// and a name's uniqueness moves to name_index, a per-user fingerprint of the plain name.
return new class extends Migration
{
    // Each table's encrypted columns. amount is never null; a null note stays null.
    private const COLUMNS = [
        'expenses' => ['amount', 'note'],
        'recurring' => ['amount', 'note'],
        'categories' => ['name'],
    ];

    private const CHUNK = 500;

    public function up(): void
    {
        $this->rebuild(function () {
            // Widen first: ciphertext doesn't fit the old types.
            foreach (['expenses', 'recurring'] as $name) {
                Schema::table($name, fn (Blueprint $table) => $table->text('amount')->change());
            }
            Schema::table('categories', function (Blueprint $table) {
                $table->dropUnique(['user_id', 'name']);
                $table->text('name')->change();
                // Nullable only until every row has one, a few lines down.
                $table->string('name_index', 64)->nullable();
            });

            foreach (self::COLUMNS as $table => $columns) {
                $this->rewrite($table, $columns, fn (string $value) => LedgerCipher::isEncrypted($value) ? $value : LedgerCipher::encrypt($value));
            }

            // Always recomputed from the decrypted name, so a re-run fixes a stale one.
            DB::table('categories')->select(['id', 'user_id', 'name'])->chunkById(self::CHUNK, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('categories')->where('id', $row->id)->update([
                        'name_index' => LedgerCipher::fingerprint((int) $row->user_id, LedgerCipher::decrypt((string) $row->name)),
                    ]);
                }
            });

            // NOT NULL from here on: a unique index lets any number of nulls through, so a
            // row without a fingerprint would silently skip the duplicate check.
            Schema::table('categories', function (Blueprint $table) {
                $table->string('name_index', 64)->nullable(false)->change();
                $table->unique(['user_id', 'name_index']);
            });
        });
    }

    // Local rollback only (production rolls back through Neon's restore window). Values
    // are decrypted while the columns are still text, then the types narrow back.
    public function down(): void
    {
        $this->rebuild(function () {
            foreach (self::COLUMNS as $table => $columns) {
                $this->rewrite($table, $columns, fn (string $value) => LedgerCipher::decrypt($value));
            }

            Schema::table('categories', function (Blueprint $table) {
                $table->dropUnique(['user_id', 'name_index']);
                $table->dropColumn('name_index');
                $table->string('name')->change();
                $table->unique(['user_id', 'name']);
            });

            foreach (['expenses', 'recurring'] as $name) {
                if (DB::getDriverName() === 'pgsql') {
                    // Postgres won't cast text to bigint implicitly; the schema builder has no USING.
                    DB::statement("ALTER TABLE {$name} ALTER COLUMN amount TYPE bigint USING amount::bigint");
                } else {
                    Schema::table($name, fn (Blueprint $table) => $table->bigInteger('amount')->change());
                }
            }
        });
    }

    /**
     * Rewrites every non-null value of the columns, soft-deleted rows included. DB::table on
     * purpose: no ownership scope (nobody is signed in), no casts, and updated_at is left
     * alone (the app reads it as "paused since" and "last changed").
     *
     * @param  list<string>  $columns
     */
    private function rewrite(string $table, array $columns, Closure $transform): void
    {
        DB::table($table)->select(['id', ...$columns])->chunkById(self::CHUNK, function ($rows) use ($table, $columns, $transform) {
            foreach ($rows as $row) {
                $changes = [];
                foreach ($columns as $column) {
                    if ($row->{$column} === null) {
                        continue;
                    }
                    $value = (string) $row->{$column};
                    $new = $transform($value);
                    if ($new !== $value) {
                        $changes[$column] = $new;
                    }
                }

                if ($changes !== []) {
                    DB::table($table)->where('id', $row->id)->update($changes);
                }
            }
        });
    }

    /**
     * SQLite can't alter a column, so change() rebuilds the table: copy into a temp table,
     * drop the original, rename. Laravel runs SQLite migrations without a transaction, so a
     * failure between the drop and the rename would lose the table, as would a failure
     * half-way through the data. As in 2026_10_13_000004: foreign keys go off first (the
     * pragma is ignored inside a transaction), then the type changes and every rewritten
     * value commit together, and only if every reference still resolves.
     */
    private function rebuild(Closure $steps): void
    {
        Schema::withoutForeignKeyConstraints(function () use ($steps) {
            DB::transaction(function () use ($steps) {
                $steps();

                if (DB::getDriverName() === 'sqlite' && DB::select('PRAGMA foreign_key_check') !== []) {
                    throw new RuntimeException('Rebuilding the ledger tables broke a foreign key; nothing was changed.');
                }
            });
        });
    }
};
