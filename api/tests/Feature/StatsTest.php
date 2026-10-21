<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Expense;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\ActsAsUser;
use Tests\TestCase;

class StatsTest extends TestCase
{
    use ActsAsUser;
    use RefreshDatabase;

    private const CATEGORY_REF = ['id', 'name', 'color', 'emoji'];

    /**
     * A fixed historical corpus so every expectation is an exact number.
     *
     * 2026-05 → 90 000 (the baseline for change_pct)
     * 2026-06 → 150 000: Food 100 000 / 3 rows, Fun 30 000 / 1, Transport 20 000 / 1
     *
     * @return array{food: Category, transport: Category, fun: Category}
     */
    private function seedCorpus(): array
    {
        $food = Category::factory()->create(['name' => 'Food', 'color' => '#C9692E', 'emoji' => '🍔']);
        $transport = Category::factory()->create(['name' => 'Transport', 'color' => '#3F6F97', 'emoji' => '🚕']);
        $fun = Category::factory()->create(['name' => 'Fun', 'color' => '#B0577B', 'emoji' => '🎈']);

        Expense::factory()->create(['category_id' => $food->id, 'date' => '2026-05-10', 'amount' => 60000]);
        Expense::factory()->create(['category_id' => $fun->id, 'date' => '2026-05-20', 'amount' => 30000]);

        Expense::factory()->create(['category_id' => $food->id, 'date' => '2026-06-01', 'amount' => 50000]);
        Expense::factory()->create(['category_id' => $food->id, 'date' => '2026-06-01', 'amount' => 30000]);
        Expense::factory()->create(['category_id' => $transport->id, 'date' => '2026-06-05', 'amount' => 20000]);
        Expense::factory()->create(['category_id' => $fun->id, 'date' => '2026-06-10', 'amount' => 30000]);
        Expense::factory()->create(['category_id' => $food->id, 'date' => '2026-06-10', 'amount' => 20000]);

        return ['food' => $food, 'transport' => $transport, 'fun' => $fun];
    }

    public function test_monthly_totals_and_change_pct_for_a_populated_month(): void
    {
        $this->seedCorpus();

        $response = $this->getJson('/api/stats/monthly?month=2026-06');

        $response->assertOk();
        $this->assertSame(['data'], array_keys($response->json()));
        $this->assertSame(
            ['month', 'total', 'previous_total', 'change_pct', 'by_category', 'daily'],
            array_keys($response->json('data'))
        );

        $response->assertJsonPath('data.month', '2026-06');
        $response->assertJsonPath('data.total', 150000);
        $response->assertJsonPath('data.previous_total', 90000);
        // (150000 - 90000) / 90000 * 100 = 66.666… → one decimal
        $response->assertJsonPath('data.change_pct', 66.7);
    }

    public function test_monthly_by_category_is_sorted_by_total_descending_with_row_counts(): void
    {
        $categories = $this->seedCorpus();

        $rows = $this->getJson('/api/stats/monthly?month=2026-06')->json('data.by_category');

        $this->assertCount(3, $rows);
        foreach ($rows as $row) {
            $this->assertSame(['category', 'total', 'count'], array_keys($row));
            $this->assertSame(self::CATEGORY_REF, array_keys($row['category']));
        }

        $this->assertSame([100000, 30000, 20000], array_column($rows, 'total'));
        $this->assertSame([3, 1, 1], array_column($rows, 'count')); // rows, not pesos
        $this->assertSame(
            [$categories['food']->id, $categories['fun']->id, $categories['transport']->id],
            array_column(array_column($rows, 'category'), 'id')
        );
    }

    public function test_monthly_daily_is_zero_filled_one_entry_per_calendar_day_in_ascending_order(): void
    {
        $this->seedCorpus();

        $daily = $this->getJson('/api/stats/monthly?month=2026-06')->json('data.daily');

        $daysInMonth = Carbon::parse('2026-06-01')->daysInMonth;
        $this->assertCount($daysInMonth, $daily);

        $expectedDates = [];
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $expectedDates[] = sprintf('2026-06-%02d', $day);
        }
        $this->assertSame($expectedDates, array_column($daily, 'date'));
    }

    // KNOWN FAILURE — a real bug, not a test problem. See the note at the foot of this file.
    public function test_monthly_daily_carries_each_days_total(): void
    {
        $this->seedCorpus();

        $daily = $this->getJson('/api/stats/monthly?month=2026-06')->json('data.daily');
        $totals = array_column($daily, 'total', 'date');

        $this->assertSame(80000, $totals['2026-06-01']); // 50000 + 30000
        $this->assertSame(20000, $totals['2026-06-05']);
        $this->assertSame(50000, $totals['2026-06-10']); // 30000 + 20000

        $zeroDays = array_diff_key($totals, array_flip(['2026-06-01', '2026-06-05', '2026-06-10']));
        $this->assertSame([0], array_values(array_unique($zeroDays)));

        // daily must reconcile with the headline total the same response reports.
        $this->assertSame(150000, array_sum($totals));
    }

    public function test_monthly_change_pct_is_null_without_a_previous_month_baseline(): void
    {
        $category = Category::factory()->create();
        Expense::factory()->create(['category_id' => $category->id, 'date' => '2026-06-05', 'amount' => 50000]);

        $response = $this->getJson('/api/stats/monthly?month=2026-06');

        $response->assertOk();
        $response->assertJsonPath('data.total', 50000);
        $response->assertJsonPath('data.previous_total', 0);
        $this->assertNull($response->json('data.change_pct'));
    }

    public function test_monthly_for_an_entirely_empty_month_is_all_zeroes_with_a_full_zero_filled_daily(): void
    {
        $this->seedCorpus();

        $response = $this->getJson('/api/stats/monthly?month=2026-02');

        $response->assertOk();
        $response->assertJsonPath('data.month', '2026-02');
        $response->assertJsonPath('data.total', 0);
        $response->assertJsonPath('data.previous_total', 0);
        $this->assertNull($response->json('data.change_pct'));
        $this->assertSame([], $response->json('data.by_category'));

        $daily = $response->json('data.daily');
        $this->assertCount(Carbon::parse('2026-02-01')->daysInMonth, $daily);
        $this->assertSame([0], array_values(array_unique(array_column($daily, 'total'))));
    }

    public function test_monthly_with_a_malformed_month_is_a_200_all_zero_result_with_an_empty_daily(): void
    {
        $this->seedCorpus();

        $response = $this->getJson('/api/stats/monthly?month=garbage');

        $response->assertOk(); // never a 500
        $response->assertJsonPath('data.month', 'garbage'); // echoed back as given
        $response->assertJsonPath('data.total', 0);
        $response->assertJsonPath('data.previous_total', 0);
        $this->assertNull($response->json('data.change_pct'));
        $this->assertSame([], $response->json('data.by_category'));
        $this->assertSame([], $response->json('data.daily'));
    }

    public function test_monthly_without_a_month_param_defaults_to_the_current_month(): void
    {
        $response = $this->getJson('/api/stats/monthly');

        $response->assertOk();
        $response->assertJsonPath('data.month', now()->format('Y-m'));
        $this->assertCount(now()->daysInMonth, $response->json('data.daily'));
    }

    public function test_trend_defaults_to_six_zero_filled_months_ending_on_the_current_month(): void
    {
        $category = Category::factory()->create();
        Expense::factory()->create([
            'category_id' => $category->id,
            'date' => now()->startOfMonth()->format('Y-m-d'),
            'amount' => 70000,
        ]);
        Expense::factory()->create([
            'category_id' => $category->id,
            'date' => now()->startOfMonth()->subMonthsNoOverflow(5)->format('Y-m-d'),
            'amount' => 40000,
        ]);

        $response = $this->getJson('/api/stats/trend');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(6, $data);

        $this->assertSame($this->expectedWindow(6), array_column($data, 'month'));
        $this->assertSame(now()->format('Y-m'), $data[5]['month']); // newest last
        $this->assertSame([40000, 0, 0, 0, 0, 70000], array_column($data, 'total'));

        foreach ($data as $entry) {
            $this->assertSame(['month', 'total'], array_keys($entry));
        }
    }

    public function test_trend_with_three_months_ends_on_the_current_month(): void
    {
        $response = $this->getJson('/api/stats/trend?months=3');

        $response->assertOk();
        $this->assertSame($this->expectedWindow(3), array_column($response->json('data'), 'month'));
    }

    public static function trendClamps(): array
    {
        return [
            'zero falls back to the default' => ['0', 6],
            'negative clamps up to one' => ['-3', 1],
            'thirty clamps down to the max' => ['30', 24],
            'a float is truncated' => ['6.7', 6],
            'non numeric falls back to the default' => ['abc', 6],
        ];
    }

    #[DataProvider('trendClamps')]
    public function test_trend_clamps_the_months_param(string $months, int $expectedCount): void
    {
        $response = $this->getJson("/api/stats/trend?months={$months}");

        $response->assertOk();
        $this->assertCount($expectedCount, $response->json('data'));
        $this->assertSame($this->expectedWindow($expectedCount), array_column($response->json('data'), 'month'));
    }

    public function test_trend_envelope_is_a_bare_data_list(): void
    {
        $response = $this->getJson('/api/stats/trend');

        $response->assertOk();
        $this->assertSame(['data'], array_keys($response->json()));
        $this->assertArrayNotHasKey('meta', $response->json());
        $this->assertIsList($response->json('data'));
    }

    public function test_trend_crosses_a_year_boundary(): void
    {
        $this->travelTo(Carbon::parse('2027-01-15'));
        Expense::factory()->create(['date' => '2026-11-03', 'amount' => 40000]);
        Expense::factory()->create(['date' => '2026-11-30', 'amount' => 5000]);
        Expense::factory()->create(['date' => '2027-01-02', 'amount' => 70000]);
        Expense::factory()->create(['date' => '2026-10-31', 'amount' => 99900]); // before the window

        $data = $this->getJson('/api/stats/trend?months=3')->assertOk()->json('data');

        $this->assertSame(['2026-11', '2026-12', '2027-01'], array_column($data, 'month'));
        $this->assertSame([45000, 0, 70000], array_column($data, 'total')); // assertSame: ints, not '45000'
    }

    public function test_aggregates_are_integers(): void
    {
        $this->seedCorpus();

        $data = $this->getJson('/api/stats/monthly?month=2026-06')->assertOk()->json('data');

        $this->assertIsInt($data['total']);
        $this->assertIsInt($data['previous_total']);
        $this->assertNotEmpty($data['by_category']);
        foreach ($data['by_category'] as $row) {
            $this->assertIsInt($row['total']);
            $this->assertIsInt($row['count']);
        }
        foreach ($data['daily'] as $day) {
            $this->assertIsInt($day['total']);
        }
    }

    /** @return array<int, string> oldest → newest, ending on the current month */
    private function expectedWindow(int $months): array
    {
        $anchor = now()->startOfMonth();

        return array_map(
            fn (int $step) => $anchor->copy()->subMonthsNoOverflow($step)->format('Y-m'),
            range($months - 1, 0)
        );
    }

    public function test_a_voided_expense_drops_out_of_the_month_totals(): void
    {
        Expense::factory()->create(['date' => '2026-06-10', 'amount' => 10000]);
        $voided = Expense::factory()->create(['date' => '2026-06-11', 'amount' => 5000]);

        $this->deleteJson("/api/expenses/{$voided->id}")->assertNoContent();

        $data = $this->getJson('/api/stats/monthly?month=2026-06')->json('data');
        $this->assertSame(10000, $data['total']);
        $this->assertSame(1, array_sum(array_column($data['by_category'], 'count')));
    }
}

/*
 * test_monthly_daily_carries_each_days_total fails against the current code, and the
 * failure is the app's, not the test's.
 *
 * `expenses.date` has a `date` cast, but Eloquent writes it with the model's datetime
 * format, so a row created through the model — which is every row the API creates —
 * lands in SQLite as '2026-06-01 00:00:00'. The seeder is the only clean-looking data
 * in the app: it uses Expense::insert(), which bypasses casts and stores '2026-06-01'.
 *
 * StatsController::daily() groups on the raw `date` column and looks the sums up by
 * keys it builds with sprintf('%s-%02d'), so the datetime-shaped keys never match and
 * every one of those days silently reads 0. `total`, `by_category` and the expense
 * month filter all use LIKE '2026-06%' and trend uses substr(date, 1, 7), so they are
 * unaffected — which is why the same response reports total 150000 over a daily that
 * sums to 0. Reproduced live: POST /api/expenses then GET /api/stats/monthly.
 */
