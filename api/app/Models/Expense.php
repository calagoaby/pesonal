<?php

namespace App\Models;

use App\Casts\EncryptedText;
use App\Casts\MoneyCast;
use App\Models\Concerns\BelongsToUser;
use App\Support\Money;
use App\Support\Month;
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
 * user_id is added in a foreach over table names, which Larastan's migration reader
 * can't follow.
 *
 * @property Carbon $date
 * @property Money $amount
 * @property int $user_id
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
            'amount' => MoneyCast::class, // centavos, bound to the Money VO, stored encrypted
            'note' => EncryptedText::class,
            // The format normalizes the WRITE too: a bare 'date' cast persists via the
            // model's datetime format, and SQLite keeps '2026-09-15 00:00:00' verbatim,
            // which stats' daily grouping then never matches.
            'date' => 'date:Y-m-d',
        ];
    }

    /**
     * Match rows whose date falls in the given 'YYYY-MM' month. A malformed month
     * simply matches nothing; it is never an error.
     *
     * A half-open range, not BETWEEN first AND last: SQLite compares dates as text, and
     * a row written before the 'date:Y-m-d' cast ('2026-06-30 00:00:00') sorts after
     * '2026-06-30' but before '2026-07-01'. And unlike LIKE, which Postgres only takes on
     * a date column through a ::text cast, a range can use the (date, id) index.
     *
     * @param  Builder<Expense>  $query
     */
    public function scopeInMonth(Builder $query, string $month): void
    {
        $start = Month::start($month);

        if ($start === null) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where('date', '>=', $start->format('Y-m-d'))
            ->where('date', '<', $start->copy()->addMonthNoOverflow()->format('Y-m-d'));
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
