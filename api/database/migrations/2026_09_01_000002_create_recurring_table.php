<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Table is `recurring` (not the pluralized model name) to match /api/recurring.
        Schema::create('recurring', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('amount'); // centavos
            $table->text('note')->nullable();
            $table->unsignedTinyInteger('day_of_month'); // 1-31, clamped to month length at materialization
            $table->date('start_date');
            $table->date('end_date')->nullable(); // null = open-ended
            $table->boolean('active')->default(true);
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring');
    }
};