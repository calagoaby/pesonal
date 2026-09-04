<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['amount', 'date', 'note', 'category_id', 'recurring_id'])]
class Expense extends Model
{
    protected function casts(): array
    {
        return [
            'amount' => 'integer', // centavos, bare int — never a float or formatted string
            // The format normalizes the WRITE too: a bare 'date' cast persists via the
            // model's datetime format, and SQLite keeps '2026-09-15 00:00:00' verbatim,
            // which stats' daily grouping then never matches.
            'date' => 'date:Y-m-d',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function recurring(): BelongsTo
    {
        return $this->belongsTo(Recurring::class);
    }
}