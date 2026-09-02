<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('amount'); // centavos
            $table->date('date'); // calendar YYYY-MM-DD, no time
            $table->text('note')->nullable();
            // restrict: deleting a referenced category must 409, never orphan expenses.
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            // No FK on purpose: deleting a template must leave its materialized expenses'
            // recurring_id intact (contract invariant), so orphaned ids are allowed.
            $table->foreignId('recurring_id')->nullable();
            $table->timestamps();

            $table->index('recurring_id'); // materialization checks (recurring_id, month) for idempotency

            // The list sorts by date desc, id desc and filters by month (a date range).
            $table->index(['date', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};