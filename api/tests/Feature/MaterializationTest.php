<?php

namespace Tests\Feature;

use App\Actions\MaterializeRecurring;
use App\Models\Category;
use App\Models\Expense;
use App\Models\Recurring;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MaterializationTest extends TestCase
{
    use RefreshDatabase;

    private function materialize(Carbon $today): int
    {
        return (new MaterializeRecurring)->execute($today);
    }

    public function test_an_arrived_due_day_creates_one_expense_carrying_the_template_fields(): void
    {
        $category = Category::factory()->create();
        $template = Recurring::factory()->create([
            'category_id' => $category->id,
            'amount' => 1500000,
            'note' => 'Apartment rent',
            'day_of_month' => 15,
            'start_date' => '2026-01-01',
        ]);

        $created = $this->materialize(Carbon::create(2026, 6, 20));

        $this->assertSame(1, $created);
        $this->assertSame(1, Expense::count());

        $expense = Expense::first();
        $this->assertSame(1500000, $expense->amount->centavos());
        $this->assertSame('2026-06-15', $expense->date->format('Y-m-d'));
        $this->assertSame('Apartment rent', $expense->note);
        $this->assertSame($category->id, $expense->category_id);
        $this->assertSame($template->id, $expense->recurring_id);
    }

    public function test_running_twice_for_the_same_month_creates_only_one_expense(): void
    {
        $template = Recurring::factory()->create(['day_of_month' => 15, 'start_date' => '2026-01-01']);

        $today = Carbon::create(2026, 6, 20);
        $this->assertSame(1, $this->materialize($today));
        $this->assertSame(0, $this->materialize($today->copy()));

        $this->assertSame(1, Expense::where('recurring_id', $template->id)->count());
    }

    public function test_a_paused_template_creates_nothing(): void
    {
        Recurring::factory()->create(['active' => false, 'day_of_month' => 15, 'start_date' => '2026-01-01']);

        $this->assertSame(0, $this->materialize(Carbon::create(2026, 6, 20)));
        $this->assertSame(0, Expense::count());
    }

    public function test_a_due_day_not_yet_arrived_creates_nothing_this_month(): void
    {
        Recurring::factory()->create(['day_of_month' => 20, 'start_date' => '2026-01-01']);

        $this->assertSame(0, $this->materialize(Carbon::create(2026, 6, 10)));
        $this->assertSame(0, Expense::count());
    }

    public function test_a_passed_end_date_stops_materializing(): void
    {
        Recurring::factory()->create([
            'day_of_month' => 10,
            'start_date' => '2026-01-01',
            'end_date' => '2026-05-31',
        ]);

        $this->assertSame(0, $this->materialize(Carbon::create(2026, 6, 15)));
        $this->assertSame(0, Expense::count());
    }

    public function test_an_end_date_later_this_month_still_materializes_when_the_due_day_precedes_it(): void
    {
        Recurring::factory()->create([
            'day_of_month' => 10,
            'start_date' => '2026-01-01',
            'end_date' => '2026-06-20',
        ]);

        $this->assertSame(1, $this->materialize(Carbon::create(2026, 6, 15)));
        $this->assertSame('2026-06-10', Expense::first()->date->format('Y-m-d'));
    }

    public function test_a_due_day_before_a_mid_month_start_date_does_not_fire_the_first_month(): void
    {
        Recurring::factory()->create([
            'day_of_month' => 5,
            'start_date' => '2026-03-20',
        ]);

        // March: due day (5) precedes the 20th start — nothing.
        $this->assertSame(0, $this->materialize(Carbon::create(2026, 3, 25)));
        $this->assertSame(0, Expense::count());

        // April: the due day now sits after the start — it fires.
        $this->assertSame(1, $this->materialize(Carbon::create(2026, 4, 25)));
        $this->assertSame('2026-04-05', Expense::first()->date->format('Y-m-d'));
    }

    public function test_day_31_clamps_to_the_last_day_of_a_30_day_month(): void
    {
        Recurring::factory()->create(['day_of_month' => 31, 'start_date' => '2026-01-01']);

        $this->assertSame(1, $this->materialize(Carbon::create(2026, 4, 30)));
        $this->assertSame('2026-04-30', Expense::first()->date->format('Y-m-d'));
    }

    public function test_day_31_clamps_to_february_29_in_a_leap_year(): void
    {
        Recurring::factory()->create(['day_of_month' => 31, 'start_date' => '2020-01-01']);

        // 2028 is a leap year.
        $this->assertSame(1, $this->materialize(Carbon::create(2028, 2, 29)));
        $this->assertSame('2028-02-29', Expense::first()->date->format('Y-m-d'));
    }

    public function test_day_31_clamps_to_february_28_in_a_non_leap_year(): void
    {
        Recurring::factory()->create(['day_of_month' => 31, 'start_date' => '2020-01-01']);

        // 2027 is not a leap year.
        $this->assertSame(1, $this->materialize(Carbon::create(2027, 2, 28)));
        $this->assertSame('2027-02-28', Expense::first()->date->format('Y-m-d'));
    }

    public function test_a_start_date_months_in_the_past_creates_only_this_months_expense(): void
    {
        $template = Recurring::factory()->create(['day_of_month' => 10, 'start_date' => '2026-01-01']);

        $created = $this->materialize(Carbon::create(2026, 6, 15));

        $this->assertSame(1, $created);
        $this->assertSame(1, Expense::where('recurring_id', $template->id)->count());
        $this->assertSame('2026-06-10', Expense::first()->date->format('Y-m-d'));
    }

    public function test_a_materialized_expense_shows_up_in_the_expenses_list_for_its_month(): void
    {
        Recurring::factory()->create(['day_of_month' => 10, 'start_date' => '2026-01-01']);

        $this->materialize(Carbon::create(2026, 6, 15));

        $this->getJson('/api/expenses?month=2026-06')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.date', '2026-06-10');
    }

    public function test_the_console_command_runs_and_reports_success(): void
    {
        $this->artisan('recurring:materialize')->assertSuccessful();
    }

    public function test_a_voided_materialized_expense_stays_voided_for_the_rest_of_the_month(): void
    {
        Recurring::factory()->create(['day_of_month' => 15, 'start_date' => '2026-01-01']);
        $this->materialize(Carbon::create(2026, 6, 20));

        Expense::first()->delete();

        $this->assertSame(0, $this->materialize(Carbon::create(2026, 6, 21)));
        $this->assertSame(0, Expense::count());
        $this->assertSame(1, Expense::withTrashed()->count());
    }

    public function test_a_void_this_month_does_not_stop_next_months_charge(): void
    {
        Recurring::factory()->create(['day_of_month' => 15, 'start_date' => '2026-01-01']);
        $this->materialize(Carbon::create(2026, 6, 20));
        Expense::first()->delete();

        $this->assertSame(1, $this->materialize(Carbon::create(2026, 7, 20)));
        $this->assertSame('2026-07-15', Expense::first()->date->format('Y-m-d'));
    }

    public function test_deleting_and_restoring_a_template_does_not_charge_the_month_twice(): void
    {
        $template = Recurring::factory()->create(['day_of_month' => 15, 'start_date' => '2026-01-01']);
        $this->materialize(Carbon::create(2026, 6, 20));

        $this->deleteJson("/api/recurring/{$template->id}")->assertNoContent();
        $this->postJson("/api/recurring/{$template->id}/restore")->assertOk();

        $this->assertSame(0, $this->materialize(Carbon::create(2026, 6, 21)));
        $this->assertSame(1, Expense::count());
    }
}
