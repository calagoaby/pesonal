<?php

namespace App\Support;

use Carbon\Carbon;

class Month
{
    /**
     * The first day of a strict 'YYYY-MM' month, or null. Carbon alone would roll
     * '2026-13' over into 2027-01, so the month is range-checked first.
     */
    public static function start(string $month): ?Carbon
    {
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            return null;
        }

        return Carbon::createFromFormat('Y-m-d', "{$month}-01")->startOfDay();
    }
}
