<?php

use App\Support\LedgerRewrite;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Amounts, notes and category names become ciphertext (APP_KEY), in place. Ciphertext is
// a long base64 string, so the bigint amounts and the varchar(255) names widen to text,
// and a name's uniqueness moves to name_index, a per-user fingerprint of the plain name.
return new class extends Migration
{
    // Frozen here: a later change to LedgerRewrite::COLUMNS must not change this migration.
    private const COLUMNS = [
        'expenses' => ['amount', 'note'],
        'recurring' => ['amount', 'note'],
        'categories' => ['name'],
    ];

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

            // An empty note becomes null, as the cast saves it; down() then can't bring back a
            // byte-exact '', though it reads the same.
            LedgerRewrite::encrypt(self::COLUMNS);

            // Always recomputed from the decrypted name, so a re-run fixes a stale one. The
            // unique index only comes next, so nothing can collide here.
            LedgerRewrite::fingerprint();

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
            // An amount that doesn't decrypt to an integer throws: nothing else reaches bigint.
            LedgerRewrite::decrypt(self::COLUMNS);

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
