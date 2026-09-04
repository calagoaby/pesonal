<?php

namespace App\Models;

use Database\Factories\RecurringFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * The 'date:Y-m-d' casts return Carbon instances; Larastan reads a formatted cast
 * as a string without these hints, so the Resource's ->format() calls trip it.
 *
 * @property Carbon $start_date
 * @property Carbon|null $end_date
 */
#[Fillable(['amount', 'note', 'day_of_month', 'start_date', 'end_date', 'active', 'category_id'])]
class Recurring extends Model
{
    /** @use HasFactory<RecurringFactory> */
    use HasFactory;

    protected $table = 'recurring';

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'day_of_month' => 'integer',
            // Format pins the stored string to YYYY-MM-DD — materialization compares these
            // as strings, and a bare 'date' cast would write a time component.
            'start_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
            // must serialize as JSON true/false, not 1/0 — the frontend type is boolean.
            'active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return HasMany<Expense, $this> */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }
}
