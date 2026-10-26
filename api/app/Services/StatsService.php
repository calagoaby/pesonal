<?php

namespace App\Services;

use App\Http\Resources\CategoryRefResource;
use App\Models\Category;
use App\Models\Expense;
use App\Support\Month;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

// Every figure is summed here, not by the database: amount is stored as ciphertext, so
// SUM and ORDER BY on it mean nothing. Rows come back through the model, so the
// ownership scope applies and the amount cast decrypts.
class StatsService
{
    private const COLUMNS = ['id', 'date', 'amount', 'category_id'];

    /**
     * @return array<string, mixed>
     */
    public function monthly(string $month): array
    {
        $monthDate = Month::start($month); // null when unparseable → all-zero, never a 500

        $rows = $this->rowsInMonth($month);
        $total = $this->sum($rows);

        $previousMonth = $monthDate?->copy()->subMonthNoOverflow()->format('Y-m');
        $previousTotal = $previousMonth ? $this->sum($this->rowsInMonth($previousMonth)) : 0;

        // No baseline last month → null, never a divide by zero.
        $changePct = $previousTotal === 0
            ? null
            : round(($total - $previousTotal) / $previousTotal * 100, 1);

        return [
            'month' => $month,
            'total' => $total,
            'previous_total' => $previousTotal,
            'change_pct' => $changePct,
            'by_category' => $this->byCategory($rows),
            // ternary, not $monthDate?->daysInMonth ?? 0: same result (null month → 0),
            // but phpstan's nullsafe.neverNull rule mis-flags the ?->…?? form here.
            'daily' => $this->daily($rows, $month, $monthDate !== null ? $monthDate->daysInMonth : 0),
        ];
    }

    /**
     * @return array<int, array{month: string, total: int}>
     */
    public function trend(int $months): array
    {
        // Clamp to 1..max. A 0/non-numeric fallback (→default) is the caller's; negatives clamp up to 1.
        $months = min(max($months, 1), (int) config('pesonal.trend.max_months'));

        // Window ends on the current month, anchored on the clock, oldest → newest.
        $anchor = now()->startOfMonth();
        $window = collect(range($months - 1, 0))
            ->map(fn (int $step) => $anchor->copy()->subMonthsNoOverflow($step)->format('Y-m'));

        // One query over the whole window (a half-open date range), folded into months
        // here, then zero-filled so the line stays contiguous.
        $rows = Expense::query()
            ->where('date', '>=', $window->first().'-01')
            ->where('date', '<', $anchor->copy()->addMonthNoOverflow()->format('Y-m-d'))
            ->get(self::COLUMNS);

        $sums = [];
        foreach ($rows as $row) {
            $ym = $row->date->format('Y-m');
            $sums[$ym] = ($sums[$ym] ?? 0) + $row->amount->centavos();
        }

        return $window->map(fn (string $ym) => [
            'month' => $ym,
            'total' => $sums[$ym] ?? 0,
        ])->values()->all();
    }

    /**
     * @return EloquentCollection<int, Expense>
     */
    private function rowsInMonth(string $month): EloquentCollection
    {
        return Expense::query()->inMonth($month)->get(self::COLUMNS);
    }

    /**
     * @param  EloquentCollection<int, Expense>  $rows
     */
    private function sum(EloquentCollection $rows): int
    {
        return $rows->sum(fn (Expense $row) => $row->amount->centavos());
    }

    /**
     * @param  EloquentCollection<int, Expense>  $rows
     * @return Collection<int, array{category: array<string, mixed>, total: int, count: int}>
     */
    private function byCategory(EloquentCollection $rows): Collection
    {
        $groups = [];
        foreach ($rows as $row) {
            $group = $groups[$row->category_id] ?? ['category_id' => $row->category_id, 'total' => 0, 'count' => 0];
            $group['total'] += $row->amount->centavos();
            $group['count']++; // a row count, not a peso figure
            $groups[$row->category_id] = $group;
        }

        // Highest total first; category_id breaks ties so the order is deterministic.
        usort($groups, fn (array $x, array $y) => [$y['total'], $x['category_id']] <=> [$x['total'], $y['category_id']]);

        $categories = Category::findMany(array_column($groups, 'category_id'))->keyBy('id');

        return collect($groups)->map(function (array $group) use ($categories) {
            /** @var array<string, mixed> $category */
            $category = CategoryRefResource::make($categories[$group['category_id']])->resolve();

            return [
                'category' => $category,
                'total' => $group['total'],
                'count' => $group['count'],
            ];
        })->values();
    }

    /**
     * @param  EloquentCollection<int, Expense>  $rows
     * @return array<int, array{date: string, total: int}> zero-filled, one per calendar day
     */
    private function daily(EloquentCollection $rows, string $month, int $daysInMonth): array
    {
        $sums = [];
        foreach ($rows as $row) {
            $date = $row->date->format('Y-m-d');
            $sums[$date] = ($sums[$date] ?? 0) + $row->amount->centavos();
        }

        $daily = [];
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $date = sprintf('%s-%02d', $month, $day);
            $daily[] = ['date' => $date, 'total' => $sums[$date] ?? 0];
        }

        return $daily;
    }
}
