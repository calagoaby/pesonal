<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLES = ['categories', 'recurring', 'expenses'];

    public function up(): void
    {
        // DB::table on purpose: no ownership scope, and soft-deleted rows count too.
        $unowned = [
            'categories' => DB::table('categories')->whereNull('user_id')->count(),
            'expenses' => DB::table('expenses')->whereNull('user_id')->count(),
            'standing orders' => DB::table('recurring')->whereNull('user_id')->count(),
        ];

        if (array_sum($unowned) > 0) {
            $counts = implode(', ', array_map(fn ($count, $label) => "{$count} {$label}", $unowned, array_keys($unowned)));

            throw new RuntimeException("{$counts} have no owner. Run php artisan pesonal:claim <email> first.");
        }

        $this->rebuild(fn (Blueprint $table) => $table->foreignId('user_id')->nullable(false)->change());
    }

    public function down(): void
    {
        $this->rebuild(fn (Blueprint $table) => $table->foreignId('user_id')->nullable()->change());
    }

    /**
     * SQLite can't alter a column, so change() rebuilds each table: copy into a temp
     * table, drop the original, rename. Laravel turns foreign keys off for that, but
     * runs the steps without a transaction (SQLite migrations aren't wrapped), so a
     * failure between the drop and the rename would lose the table. Keys go off first
     * (the pragma is ignored inside a transaction), then all three rebuilds commit
     * together, and only if every reference still resolves.
     */
    private function rebuild(Closure $change): void
    {
        Schema::withoutForeignKeyConstraints(function () use ($change) {
            DB::transaction(function () use ($change) {
                foreach (self::TABLES as $name) {
                    Schema::table($name, $change);
                }

                if (DB::getDriverName() === 'sqlite' && DB::select('PRAGMA foreign_key_check') !== []) {
                    throw new RuntimeException('Rebuilding the ledger tables broke a foreign key; nothing was changed.');
                }
            });
        });
    }
};
