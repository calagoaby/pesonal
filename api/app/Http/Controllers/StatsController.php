<?php

namespace App\Http\Controllers;

use App\Http\Resources\CategoryRefResource;
use App\Models\Category;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StatsController extends Controller
{
    private const MAX_MONTHS = 24;

    public function monthly(Request $request): JsonResponse
    {
        // Default to the current month. The mock hardcodes 2026-06 (a seed artifact); the
        // frontend passes ?month explicitly anyway, so this default is only a fallback.
        $month = $request->query('month') ?: now()->format('Y-m');
        $monthDate = $this->parseMonth($month); // null when unparseable → all-zero, never a 500

        $total = $this->sumForMonth($month);

        $previousMonth = $monthDate?->copy()->subMonthNoOverflow()->format('Y-m');
        $previousTotal = $previousMonth ? $this->sumForMonth($previousMonth) : 0;

        // No baseline last month → null, never a divide by zero.
        $changePct = $previousTotal === 0
            ? null
            : round(($total - $previousTotal) / $previousTotal * 100, 1);

        return response()->json([
            'data' => [
                'month' => $month,
                'total' => $total,
                'previous_total' => $previousTotal,
                'change_pct' => $changePct,
                'by_category' => $this->byCategory($month),
                'daily' => $this->daily($month, $monthDate?->daysInMonth ?? 0),
            ],
        ]);
    }

    public function trend(Request $request): JsonResponse
    {
        // 0 / empty / non-numeric → 6 (falsy), then clamp to 1..24. Negatives clamp up to 1.
        $months = (int) $request->query('months') ?: 6;
        $months = min(max($months, 1), self::MAX_MONTHS);

        // Window ends on the current month, anchored on the clock, oldest → newest.
        $anchor = now()->startOfMonth();
        $window = collect(range($months - 1, 0))
            ->map(fn (int $step) => $anchor->copy()->subMonthsNoOverflow($step)->format('Y-m'));

        // One grouped query over the window, then zero-fill so the line stays contiguous.
        $sums = DB::table('expenses')
            ->where('date', '>=', $window->first().'-01')
            ->selectRaw('substr(date, 1, 7) as ym, SUM(amount) as total')
            ->groupBy('ym')
            ->pluck('total', 'ym');

        $data = $window->map(fn (string $ym) => [
            'month' => $ym,
            'total' => (int) ($sums[$ym] ?? 0),
        ])->values();

        return response()->json(['data' => $data]);
    }

    private function sumForMonth(string $month): int
    {
        return (int) DB::table('expenses')->where('date', 'like', "$month%")->sum('amount');
    }

    /** @return \Illuminate\Support\Collection<int, array{category: array, total: int, count: int}> */
    private function byCategory(string $month)
    {
        $rows = DB::table('expenses')
            ->where('date', 'like', "$month%")
            ->selectRaw('category_id, SUM(amount) as total, COUNT(*) as count')
            ->groupBy('category_id')
            ->orderByDesc('total')
            ->orderBy('category_id') // deterministic tie-break; the frontend doesn't depend on it
            ->get();

        $categories = Category::findMany($rows->pluck('category_id'))->keyBy('id');

        return $rows->map(fn ($row) => [
            'category' => CategoryRefResource::make($categories[$row->category_id])->resolve(),
            'total' => (int) $row->total,
            'count' => (int) $row->count, // a row count, not a peso figure
        ])->values();
    }

    /** @return array<int, array{date: string, total: int}> zero-filled, one per calendar day */
    private function daily(string $month, int $daysInMonth): array
    {
        $sums = DB::table('expenses')
            ->where('date', 'like', "$month%")
            ->selectRaw('date, SUM(amount) as total')
            ->groupBy('date')
            ->pluck('total', 'date');

        $daily = [];
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $date = sprintf('%s-%02d', $month, $day);
            $daily[] = ['date' => $date, 'total' => (int) ($sums[$date] ?? 0)];
        }

        return $daily;
    }

    private function parseMonth(string $month): ?Carbon
    {
        if (! preg_match('/^\d{4}-\d{2}$/', $month)) {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        } catch (\Throwable) {
            return null;
        }
    }
}