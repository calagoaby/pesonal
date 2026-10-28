<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Nullable so the previous container, still inserting without items during a deploy, keeps working.
    public function up(): void
    {
        foreach (['expenses', 'recurring'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->text('items')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['expenses', 'recurring'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropColumn('items');
            });
        }
    }
};
