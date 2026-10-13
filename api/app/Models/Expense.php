<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Models\Concerns\BelongsToUser;
use App\Support\Money;
use Database\Factories\ExpenseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * The 'date:Y-m-d' cast returns a Carbon instance; Larastan reads the formatted
 * cast as a string without this hint, so the Resource's ->format() call trips it.
 *
 * @property Carbon $date
 * @property Money $amount
 */
#[Fillable(['amount', 'date', 'note', 'category_id', 'recurring_id'])]
class Expense extends Model
{
    use BelongsToUser;

    /** @use HasFactory<ExpenseFactory> */
    use HasFactory;

    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'amount' => MoneyCast::class, // centavos, bound to the Money VO
            // The format normalizes the WRITE too: a bare 'date' cast persists via the
            // model's datetime format, and SQLite keeps '2026-09-15 00:00:00' verbatim,
            // which stats' daily grouping then never matches.
            'date' => 'date:Y-m-d',
        ];
    }

    /**
     * Match rows whose date falls in the given 'YYYY-MM' month. A malformed month
     * simply matches nothing (LIKE 'garbage%'); it is never an error.
     *
     * @param  Builder<Expense>  $query
     */
    public function scopeInMonth(Builder $query, string $month): void
    {
        $query->where('date', 'like', "{$month}%");
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
