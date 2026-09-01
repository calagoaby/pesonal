<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['amount', 'note', 'day_of_month', 'start_date', 'end_date', 'active', 'category_id'])]
class Recurring extends Model
{
    protected $table = 'recurring';

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'day_of_month' => 'integer',
            'start_date' => 'date',
            'end_date' => 'date',
            // must serialize as JSON true/false, not 1/0 — the frontend type is boolean.
            'active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }
}