<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Nullable for now: rows from before accounts existed have no owner until
    // `pesonal:claim` hands them to one. Task 7 makes the column NOT NULL.
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            // Names are unique per ledger, not across everyone's. The pair's index also
            // serves user_id lookups (it leads), so the column needs no index of its own.
            $table->dropUnique(['name']);
            $table->unique(['user_id', 'name']);
        });

        foreach (['recurring', 'expenses'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->foreignId('user_id')->nullable()->after('id')->index()->constrained()->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['expenses', 'recurring'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropForeign(['user_id']);
                $table->dropIndex(['user_id']);
                $table->dropColumn('user_id');
            });
        }

        Schema::table('categories', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'name']);
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
            $table->unique('name');
        });
    }
};
