<?php

namespace App\Models;

use Database\Factories\ExpenseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The 'date:Y-m-d' cast returns a Carbon instance; Larastan reads the formatted
 * cast as a string without this hint, so the Resource's ->format() call trips it.
 *
 * @property Carbon $date
 */
#[Fillable(['amount', 'date', 'note', 'category_id', 'recurring_id'])]
class Expense extends Model
{
    /** @use HasFactory<ExpenseFactory> */
    use HasFactory;

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

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return BelongsTo<Recurring, $this> */
    public function recurring(): BelongsTo
    {
        return $this->belongsTo(Recurring::class);
    }
}
