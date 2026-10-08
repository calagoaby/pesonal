<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // A void hides the row instead of erasing it: Undo restores the same row, and the
    // materializer can still see that a month was already charged.
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('recurring', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('recurring', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
