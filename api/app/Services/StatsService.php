<?php

namespace App\Services;

use App\Http\Resources\CategoryRefResource;
use App\Models\Category;
use App\Models\Expense;
use App\Support\Month;
use Illuminate\Support\Collection;

class StatsService
{
    /**
     * @return array<string, mixed>
     */
    public function monthly(string $month): array
    {
        $monthDate = Month::start($month); // null when unparseable → all-zero, never a 500

        $total = $this->sumForMonth($month);

        $previousMonth = $monthDate?->copy()->subMonthNoOverflow()->format('Y-m');
        $previousTotal = $previousMonth ? $this->sumForMonth($previousMonth) : 0;

        // No baseline last month → null, never a divide by zero.
        $changePct = $previousTotal === 0
            ? null
            : round(($total - $previousTotal) / $previousTotal * 100, 1);

        return [
            'month' => $month,
            'total' => $total,
            'previous_total' => $previousTotal,
            'change_pct' => $changePct,
            'by_category' => $this->byCategory($month),
            // ternary, not $monthDate?->daysInMonth ?? 0: same result (null month → 0),
            // but phpstan's nullsafe.neverNull rule mis-flags the ?->…?? form here.
            'daily' => $this->daily($month, $monthDate !== null ? $monthDate->daysInMonth : 0),
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

        // One query grouped by day over the window, folded into months here: no substr or
        // date function, so it reads the same on SQLite and Postgres. Then zero-fill so the
        // line stays contiguous. The raw 'date' alias (ymd) keeps the key a plain string,
        // never the Carbon the date cast would hand back.
        $days = Expense::query()
            ->where('date', '>=', $window->first().'-01')
            ->selectRaw('date as ymd, SUM(amount) as total')
            ->groupBy('date')
            ->pluck('total', 'ymd');

        $sums = [];
        foreach ($days as $ymd => $total) {
            $ym = substr((string) $ymd, 0, 7);
            $sums[$ym] = ($sums[$ym] ?? 0) + (int) $total; // Postgres hands SUM back as a string
        }

        return $window->map(fn (string $ym) => [
            'month' => $ym,
            'total' => (int) ($sums[$ym] ?? 0),
        ])->values()->all();
    }

    private function sumForMonth(string $month): int
    {
        return (int) Expense::query()->inMonth($month)->sum('amount');
    }

    /**
     * @return Collection<int, array{category: array<string, mixed>, total: int, count: int}>
     */
    private function byCategory(string $month): Collection
    {
        $rows = Expense::query()
            ->inMonth($month)
            ->selectRaw('category_id, SUM(amount) as total, COUNT(*) as count')
            ->groupBy('category_id')
            ->orderByDesc('total')
            ->orderBy('category_id') // deterministic tie-break; the frontend doesn't depend on it
            ->get();

        $categories = Category::findMany($rows->pluck('category_id'))->keyBy('id');

        return $rows->map(function (Expense $row) use ($categories) {
            /** @var array<string, mixed> $category */
            $category = CategoryRefResource::make($categories[$row->category_id])->resolve();

            // total/count are selectRaw aggregate aliases, not real columns, so they are
            // read as attributes rather than typed properties.
            return [
                'category' => $category,
                'total' => (int) $row->getAttribute('total'),
                'count' => (int) $row->getAttribute('count'), // a row count, not a peso figure
            ];
        })->values();
    }

    /**
     * @return array<int, array{date: string, total: int}> zero-filled, one per calendar day
     */
    private function daily(string $month, int $daysInMonth): array
    {
        // Alias the raw 'date' column (ymd) before grouping. The 'date:Y-m-d' cast returns a
        // Carbon on read, so plucking by 'date' would key on a reformatted value and every
        // day's lookup below would miss (→ all zeroes). The alias keeps the key a plain string.
        $sums = Expense::query()
            ->inMonth($month)
            ->selectRaw('date as ymd, SUM(amount) as total')
            ->groupBy('date')
            ->pluck('total', 'ymd');

        $daily = [];
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $date = sprintf('%s-%02d', $month, $day);
            $daily[] = ['date' => $date, 'total' => (int) ($sums[$date] ?? 0)];
        }

        return $daily;
    }
}
