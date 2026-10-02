<?php

namespace App\Http\Controllers;

use App\Services\StatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StatsController extends Controller
{
    public function __construct(private readonly StatsService $stats) {}

    public function monthly(Request $request): JsonResponse
    {
        // Default to the current month. The mock hardcodes 2026-06 (a seed artifact); the
        // frontend passes ?month explicitly anyway, so this default is only a fallback.
        $month = $request->query('month') ?: now()->format('Y-m');

        return response()->json(['data' => $this->stats->monthly($month)]);
    }

    public function trend(Request $request): JsonResponse
    {
        // 0 / empty / non-numeric → default (falsy); the service clamps to 1..max.
        $months = (int) $request->query('months') ?: (int) config('pesonal.trend.default_months');

        return response()->json(['data' => $this->stats->trend($months)]);
    }
}
