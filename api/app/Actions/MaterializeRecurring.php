<?php

namespace App\Actions;

use App\Models\Expense;
use App\Models\Recurring;
use App\Models\Scopes\OwnedByCurrentUser;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class MaterializeRecurring
{
    /**
     * Turn every due recurring template into an Expense for $today's month.
     *
     * $today is injected, never read from now() inside, so a test can drive the
     * job at any fixed date deterministically. Returns the number of rows created.
     */
    public function execute(CarbonInterface $today): int
    {
        $month = $today->format('Y-m');
        $daysInMonth = $today->daysInMonth;

        $created = 0;

        foreach ($this->liveTemplates($today) as $template) {
            // Clamp: a day-31 order fires on the last day of a shorter month
            // (Feb 28/29, Apr 30, ...). Carbon's daysInMonth is leap-aware.
            $dueDay = min($template->day_of_month, $daysInMonth);
            $targetDate = sprintf('%s-%02d', $month, $dueDay);

            $arrived = $today->day >= $dueDay;
            // Guards the first month: a due day before a mid-month start_date
            // must not fire (targetDate would precede the template's start).
            $afterStart = $targetDate >= $template->start_date->format('Y-m-d');

            // Idempotency by month, not by exact date: the job re-evaluates the same
            // month all month long, and an already-materialized (even re-clamped)
            // occurrence counts as done — including a voided one, or voiding this
            // month's rent would bring it straight back on the next run.
            $alreadyDone = Expense::withoutGlobalScope(OwnedByCurrentUser::class)
                ->withTrashed()
                ->where('recurring_id', $template->id)
                ->inMonth($month)
                ->exists();

            if (! $arrived || ! $afterStart || $alreadyDone) {
                continue;
            }

            $expense = new Expense([
                'amount' => $template->amount,
                'date' => $targetDate,
                'note' => $template->note,
                'category_id' => $template->category_id,
                'recurring_id' => $template->id,
            ]);
            // The scheduler runs with nobody signed in: each charge belongs to its order's owner.
            $expense->user_id = $template->user_id;
            $expense->save();

            $created++;
        }

        return $created;
    }

    /**
     * Templates live for $today: active, started on/before today, and not past
     * their end_date (inclusive). Dates are stored as bare YYYY-MM-DD strings,
     * so the comparisons are against $today's date string.
     *
     * @return Collection<int, Recurring>
     */
    private function liveTemplates(CarbonInterface $today): Collection
    {
        $date = $today->format('Y-m-d');

        // Every user's orders: the job runs for all of them, with nobody signed in.
        return Recurring::withoutGlobalScope(OwnedByCurrentUser::class)
            ->where('active', true)
            ->where('start_date', '<=', $date)
            ->where(function ($query) use ($date) {
                $query->whereNull('end_date')
                    ->orWhere('end_date', '>=', $date);
            })
            ->get();
    }
}
