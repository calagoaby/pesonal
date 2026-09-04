<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Expense;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ExpenseTest extends TestCase
{
    use RefreshDatabase;

    private const SHAPE = ['id', 'amount', 'date', 'note', 'category', 'recurring_id', 'created_at', 'updated_at'];

    private const CATEGORY_REF = ['id', 'name', 'color', 'emoji'];

    public function test_index_envelope_is_exactly_data_and_meta_with_the_four_meta_keys(): void
    {
        Expense::factory()->create();

        $response = $this->getJson('/api/expenses');

        $response->assertOk();
        $this->assertSame(['data', 'meta'], array_keys($response->json()));
        $this->assertSame(['current_page', 'per_page', 'total', 'last_page'], array_keys($response->json('meta')));
        $this->assertSame(20, $response->json('meta.per_page'));
    }

    public function test_index_row_shape_carries_a_four_key_category_ref_and_a_calendar_date(): void
    {
        $category = Category::factory()->create(['name' => 'Food', 'color' => '#C9692E', 'emoji' => '🍔']);
        Expense::factory()->create(['category_id' => $category->id, 'date' => '2026-06-17']);

        $response = $this->getJson('/api/expenses');

        $row = $response->json('data.0');
        $this->assertSame(self::SHAPE, array_keys($row));
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $row['date']);
        $this->assertSame('2026-06-17', $row['date']);
        $this->assertSame(self::CATEGORY_REF, array_keys($row['category']));
        $this->assertArrayNotHasKey('created_at', $row['category']);
        $this->assertArrayNotHasKey('category_id', $row);
    }

    public function test_index_is_sorted_by_date_desc_then_id_desc(): void
    {
        $category = Category::factory()->create();

        $older = Expense::factory()->create(['category_id' => $category->id, 'date' => '2026-06-01']);
        $sameDayFirst = Expense::factory()->create(['category_id' => $category->id, 'date' => '2026-06-10']);
        $sameDaySecond = Expense::factory()->create(['category_id' => $category->id, 'date' => '2026-06-10']);

        $response = $this->getJson('/api/expenses');

        $this->assertSame(
            [$sameDaySecond->id, $sameDayFirst->id, $older->id],
            array_column($response->json('data'), 'id')
        );
    }

    public function test_index_month_filter_returns_only_that_month(): void
    {
        $category = Category::factory()->create();
        $inJune = Expense::factory()->create(['category_id' => $category->id, 'date' => '2026-06-15']);
        Expense::factory()->create(['category_id' => $category->id, 'date' => '2026-05-15']);
        Expense::factory()->create(['category_id' => $category->id, 'date' => '2026-07-01']);

        $response = $this->getJson('/api/expenses?month=2026-06');

        $response->assertOk();
        $this->assertSame([$inJune->id], array_column($response->json('data'), 'id'));
        $this->assertSame(1, $response->json('meta.total'));
    }

    public function test_index_with_a_malformed_month_matches_nothing_but_still_reports_last_page_one(): void
    {
        Expense::factory()->count(3)->create();

        $response = $this->getJson('/api/expenses?month=garbage');

        $response->assertOk();
        $this->assertSame([], $response->json('data'));
        $this->assertSame(0, $response->json('meta.total'));
        $this->assertSame(1, $response->json('meta.last_page')); // not 0
    }

    public function test_index_category_filter_treats_zero_and_non_numeric_as_no_filter(): void
    {
        $food = Category::factory()->create();
        $fun = Category::factory()->create();
        $foodExpense = Expense::factory()->create(['category_id' => $food->id]);
        Expense::factory()->create(['category_id' => $fun->id]);

        $filtered = $this->getJson("/api/expenses?category_id={$food->id}");
        $filtered->assertOk();
        $this->assertSame([$foodExpense->id], array_column($filtered->json('data'), 'id'));

        $this->assertSame(2, $this->getJson('/api/expenses?category_id=0')->json('meta.total'));
        $this->assertSame(2, $this->getJson('/api/expenses?category_id=abc')->json('meta.total'));
    }

    public function test_index_with_an_unknown_category_id_is_an_empty_200(): void
    {
        Expense::factory()->create();

        $response = $this->getJson('/api/expenses?category_id=999');

        $response->assertOk();
        $this->assertSame([], $response->json('data'));
        $this->assertSame(0, $response->json('meta.total'));
    }

    public function test_index_clamps_per_page_to_one_hundred(): void
    {
        $response = $this->getJson('/api/expenses?per_page=200');

        $response->assertOk();
        $this->assertSame(100, $response->json('meta.per_page'));
    }

    public function test_index_falls_back_to_twenty_for_an_invalid_per_page(): void
    {
        $this->assertSame(20, $this->getJson('/api/expenses?per_page=0')->json('meta.per_page'));
        $this->assertSame(20, $this->getJson('/api/expenses?per_page=abc')->json('meta.per_page'));
    }

    public function test_index_page_beyond_the_last_page_echoes_the_requested_page(): void
    {
        Expense::factory()->count(3)->create();

        $response = $this->getJson('/api/expenses?page=99');

        $response->assertOk();
        $this->assertSame([], $response->json('data'));
        $this->assertSame(99, $response->json('meta.current_page'));
        $this->assertSame(3, $response->json('meta.total'));
    }

    public function test_index_meta_total_is_a_row_count_not_a_sum_of_amount(): void
    {
        $category = Category::factory()->create();
        Expense::factory()->create(['category_id' => $category->id, 'amount' => 100000]);
        Expense::factory()->create(['category_id' => $category->id, 'amount' => 250000]);

        $response = $this->getJson('/api/expenses');

        $this->assertSame(2, $response->json('meta.total')); // not 350000
    }

    public function test_show_returns_the_nested_shape_and_a_missing_id_404s(): void
    {
        $expense = Expense::factory()->create();

        $found = $this->getJson("/api/expenses/{$expense->id}");
        $found->assertOk();
        $this->assertSame(['data'], array_keys($found->json()));
        $this->assertSame(self::SHAPE, array_keys($found->json('data')));
        $this->assertSame(self::CATEGORY_REF, array_keys($found->json('data.category')));

        $missing = $this->getJson('/api/expenses/999');
        $missing->assertNotFound();
        $this->assertSame('That entry is not on the roll.', $missing->json('message'));
    }

    public function test_store_trims_the_note_and_never_takes_recurring_id_from_the_body(): void
    {
        $category = Category::factory()->create();

        $response = $this->postJson('/api/expenses', [
            'amount' => 15000,
            'date' => '2026-08-15',
            'category_id' => $category->id,
            'note' => '  lunch with team  ',
            'recurring_id' => 42,
        ]);

        $response->assertCreated();
        $this->assertSame(self::SHAPE, array_keys($response->json('data')));
        $response->assertJsonPath('data.note', 'lunch with team');
        $response->assertJsonPath('data.recurring_id', null);
        $response->assertJsonPath('data.date', '2026-08-15');
        $response->assertJsonPath('data.amount', 15000);
        $this->assertDatabaseHas('expenses', [
            'id' => $response->json('data.id'),
            'recurring_id' => null,
            'note' => 'lunch with team',
        ]);
    }

    public static function invalidStorePayloads(): array
    {
        return [
            'float amount' => ['amount', 150.5, 'amount', 'The amount field must be a positive number of centavos.'],
            'numeric string amount' => ['amount', '150', 'amount', 'The amount field must be a positive number of centavos.'],
            'zero amount' => ['amount', 0, 'amount', 'The amount field must be a positive number of centavos.'],
            'slashed date' => ['date', '2026/08/15', 'date', 'The date field must be a YYYY-MM-DD calendar date.'],
            'unknown category' => ['category_id', 999, 'category_id', 'The selected category is invalid.'],
        ];
    }

    #[DataProvider('invalidStorePayloads')]
    public function test_store_validation_messages(string $field, mixed $value, string $errorKey, string $message): void
    {
        $category = Category::factory()->create();

        $payload = array_merge([
            'amount' => 15000,
            'date' => '2026-08-15',
            'category_id' => $category->id,
        ], [$field => $value]);

        $response = $this->postJson('/api/expenses', $payload);

        $response->assertStatus(422);
        $this->assertSame('The given data was invalid.', $response->json('message'));
        $this->assertSame($message, $response->json("errors.{$errorKey}.0"));
    }

    public function test_store_with_an_empty_body_reports_all_three_required_errors(): void
    {
        $response = $this->postJson('/api/expenses', []);

        $response->assertStatus(422);
        $this->assertSame('The amount field must be a positive number of centavos.', $response->json('errors.amount.0'));
        $this->assertSame('The date field must be a YYYY-MM-DD calendar date.', $response->json('errors.date.0'));
        $this->assertSame('The selected category is invalid.', $response->json('errors.category_id.0'));
    }

    public function test_patch_with_only_an_amount_leaves_the_other_fields_alone(): void
    {
        $category = Category::factory()->create();
        $expense = Expense::factory()->create([
            'category_id' => $category->id,
            'amount' => 15000,
            'date' => '2026-06-17',
            'note' => 'lunch',
        ]);

        $response = $this->patchJson("/api/expenses/{$expense->id}", ['amount' => 99900]);

        $response->assertOk();
        $response->assertJsonPath('data.amount', 99900);
        $response->assertJsonPath('data.date', '2026-06-17');
        $response->assertJsonPath('data.note', 'lunch');
        $response->assertJsonPath('data.category.id', $category->id);
    }

    public function test_patch_with_an_explicit_null_note_clears_it(): void
    {
        $expense = Expense::factory()->create(['note' => 'lunch']);

        $response = $this->patchJson("/api/expenses/{$expense->id}", ['note' => null]);

        $response->assertOk();
        $response->assertJsonPath('data.note', null);
        $this->assertNull($expense->fresh()->note);
    }

    public function test_patch_without_a_note_key_leaves_the_note_untouched(): void
    {
        $expense = Expense::factory()->create(['note' => 'lunch']);

        $response = $this->patchJson("/api/expenses/{$expense->id}", ['amount' => 12345]);

        $response->assertOk();
        $response->assertJsonPath('data.note', 'lunch');
        $this->assertSame('lunch', $expense->fresh()->note);
    }

    public function test_patch_with_a_note_string_sets_it(): void
    {
        $expense = Expense::factory()->create(['note' => null]);

        $response = $this->patchJson("/api/expenses/{$expense->id}", ['note' => 'merienda']);

        $response->assertOk();
        $response->assertJsonPath('data.note', 'merienda');
        $this->assertSame('merienda', $expense->fresh()->note);
    }

    public function test_patch_of_a_missing_id_404s_before_validating_the_body(): void
    {
        $response = $this->patchJson('/api/expenses/999', ['amount' => 150.5]);

        $response->assertNotFound();
        $this->assertSame('That entry is not on the roll.', $response->json('message'));
        $this->assertArrayNotHasKey('errors', $response->json());
    }

    // Read the raw column, never the model — the date cast would re-parse a dirty
    // '2026-08-15 00:00:00' back into a clean Carbon and hide the bug. Stats' daily
    // buckets group on this exact string, so a time component zeroes every day.
    public function test_store_writes_the_date_column_as_a_bare_calendar_date(): void
    {
        $category = Category::factory()->create();

        $response = $this->postJson('/api/expenses', [
            'amount' => 15000,
            'date' => '2026-08-15',
            'category_id' => $category->id,
        ]);

        $response->assertCreated();
        $stored = DB::table('expenses')->where('id', $response->json('data.id'))->value('date');
        $this->assertSame('2026-08-15', $stored);
    }

    public function test_patching_the_date_keeps_the_column_a_bare_calendar_date(): void
    {
        $expense = Expense::factory()->create(['date' => '2026-06-15']);

        $response = $this->patchJson("/api/expenses/{$expense->id}", ['date' => '2026-07-02']);

        $response->assertOk();
        $this->assertSame('2026-07-02', DB::table('expenses')->where('id', $expense->id)->value('date'));
    }

    public function test_destroy_returns_204_and_the_row_is_gone(): void
    {
        $expense = Expense::factory()->create();

        $response = $this->deleteJson("/api/expenses/{$expense->id}");

        $response->assertNoContent();
        $this->assertSame('', $response->getContent());
        $this->assertDatabaseMissing('expenses', ['id' => $expense->id]);
        $this->getJson("/api/expenses/{$expense->id}")->assertNotFound();
    }
}