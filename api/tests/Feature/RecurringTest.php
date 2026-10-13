<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Expense;
use App\Models\Recurring;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\ActsAsUser;
use Tests\TestCase;

class RecurringTest extends TestCase
{
    use ActsAsUser;
    use RefreshDatabase;

    private const SHAPE = [
        'id', 'amount', 'note', 'day_of_month', 'start_date', 'end_date',
        'active', 'category', 'created_at', 'updated_at',
    ];

    private const CATEGORY_REF = ['id', 'name', 'color', 'emoji'];

    public function test_index_is_a_bare_data_envelope_ordered_by_id_with_the_ten_key_shape(): void
    {
        $category = Category::factory()->create();
        $first = Recurring::factory()->create(['category_id' => $category->id]);
        $second = Recurring::factory()->create(['category_id' => $category->id]);

        $response = $this->getJson('/api/recurring');

        $response->assertOk();
        $this->assertSame(['data'], array_keys($response->json()));
        $this->assertArrayNotHasKey('meta', $response->json());
        $this->assertSame([$first->id, $second->id], array_column($response->json('data'), 'id'));

        foreach ($response->json('data') as $row) {
            $this->assertSame(self::SHAPE, array_keys($row));
            $this->assertSame(self::CATEGORY_REF, array_keys($row['category']));
        }
    }

    public function test_active_is_a_real_boolean_and_a_null_note_is_emitted_explicitly(): void
    {
        $category = Category::factory()->create();
        Recurring::factory()->create(['category_id' => $category->id, 'active' => false, 'note' => null, 'end_date' => null]);
        Recurring::factory()->create(['category_id' => $category->id, 'active' => true, 'end_date' => '2026-12-01']);

        $response = $this->getJson('/api/recurring');
        $rows = $response->json('data');

        $this->assertIsBool($rows[0]['active']);
        $this->assertFalse($rows[0]['active']);
        $this->assertIsBool($rows[1]['active']);
        $this->assertTrue($rows[1]['active']);

        $this->assertArrayHasKey('note', $rows[0]);
        $this->assertNull($rows[0]['note']);

        $this->assertNull($rows[0]['end_date']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $rows[1]['end_date']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $rows[1]['start_date']);
    }

    public function test_show_returns_the_shape_and_a_missing_id_404s(): void
    {
        $recurring = Recurring::factory()->create();

        $found = $this->getJson("/api/recurring/{$recurring->id}");
        $found->assertOk();
        $this->assertSame(['data'], array_keys($found->json()));
        $this->assertSame(self::SHAPE, array_keys($found->json('data')));

        $missing = $this->getJson('/api/recurring/999');
        $missing->assertNotFound();
        $this->assertSame('That standing order is gone.', $missing->json('message'));
    }

    public function test_store_defaults_active_true_note_null_end_date_null_and_keeps_day_31_as_authored(): void
    {
        $category = Category::factory()->create();

        $response = $this->postJson('/api/recurring', [
            'amount' => 1500000,
            'category_id' => $category->id,
            'day_of_month' => 31,
            'start_date' => '2026-01-01',
        ]);

        $response->assertCreated();
        $this->assertSame(self::SHAPE, array_keys($response->json('data')));
        $response->assertJsonPath('data.active', true);
        $response->assertJsonPath('data.note', null);
        $response->assertJsonPath('data.end_date', null);
        $response->assertJsonPath('data.day_of_month', 31); // stored as authored, clamping is a materialization concern
        $this->assertDatabaseHas('recurring', ['id' => $response->json('data.id'), 'day_of_month' => 31]);
    }

    public function test_store_honours_an_explicit_false_active_a_note_and_an_end_date(): void
    {
        $category = Category::factory()->create();

        $response = $this->postJson('/api/recurring', [
            'amount' => 1500000,
            'category_id' => $category->id,
            'day_of_month' => 1,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-01',
            'note' => 'Apartment rent',
            'active' => false,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.active', false);
        $response->assertJsonPath('data.note', 'Apartment rent');
        $response->assertJsonPath('data.end_date', '2026-12-01');
    }

    public static function invalidStorePayloads(): array
    {
        return [
            'float amount' => [['amount' => 150.5], 'amount', 'The amount field must be a positive number of centavos.'],
            'day zero' => [['day_of_month' => 0], 'day_of_month', 'The day of month must be between 1 and 31.'],
            'day thirty two' => [['day_of_month' => 32], 'day_of_month', 'The day of month must be between 1 and 31.'],
            'dd-mm-yyyy start date' => [['start_date' => '01-01-2026'], 'start_date', 'The start date must be a YYYY-MM-DD calendar date.'],
            'end before start' => [['end_date' => '2025-12-01'], 'end_date', 'The end date must be on or after the start date.'],
        ];
    }

    #[DataProvider('invalidStorePayloads')]
    public function test_store_validation_messages(array $override, string $errorKey, string $message): void
    {
        $category = Category::factory()->create();

        $payload = array_merge([
            'amount' => 1500000,
            'category_id' => $category->id,
            'day_of_month' => 1,
            'start_date' => '2026-01-01',
        ], $override);

        $response = $this->postJson('/api/recurring', $payload);

        $response->assertStatus(422);
        $this->assertSame('The given data was invalid.', $response->json('message'));
        $this->assertSame($message, $response->json("errors.{$errorKey}.0"));
    }

    public function test_store_with_an_empty_body_reports_the_four_required_errors(): void
    {
        $response = $this->postJson('/api/recurring', []);

        $response->assertStatus(422);
        $this->assertSame('The amount field must be a positive number of centavos.', $response->json('errors.amount.0'));
        $this->assertSame('The selected category is invalid.', $response->json('errors.category_id.0'));
        $this->assertSame('The day of month must be between 1 and 31.', $response->json('errors.day_of_month.0'));
        $this->assertSame('The start date must be a YYYY-MM-DD calendar date.', $response->json('errors.start_date.0'));
    }

    public function test_store_allows_an_end_date_equal_to_the_start_date(): void
    {
        $category = Category::factory()->create();

        $response = $this->postJson('/api/recurring', [
            'amount' => 1500000,
            'category_id' => $category->id,
            'day_of_month' => 1,
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-01',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.end_date', '2026-01-01');
    }

    public function test_patch_with_only_an_amount_leaves_the_other_fields_alone(): void
    {
        $category = Category::factory()->create();
        $recurring = Recurring::factory()->create([
            'category_id' => $category->id,
            'amount' => 1500000,
            'note' => 'Apartment rent',
            'day_of_month' => 5,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-01',
            'active' => true,
        ]);

        $response = $this->patchJson("/api/recurring/{$recurring->id}", ['amount' => 1600000]);

        $response->assertOk();
        $response->assertJsonPath('data.amount', 1600000);
        $response->assertJsonPath('data.note', 'Apartment rent');
        $response->assertJsonPath('data.day_of_month', 5);
        $response->assertJsonPath('data.start_date', '2026-01-01');
        $response->assertJsonPath('data.end_date', '2026-12-01');
        $response->assertJsonPath('data.active', true);
        $response->assertJsonPath('data.category.id', $category->id);
    }

    public function test_patch_active_false_turns_it_off_null_leaves_it_and_true_turns_it_on(): void
    {
        $recurring = Recurring::factory()->create(['active' => true]);

        $off = $this->patchJson("/api/recurring/{$recurring->id}", ['active' => false]);
        $off->assertOk();
        $off->assertJsonPath('data.active', false);

        // A null `active` is not a value — it must not flip the flag back on.
        $untouched = $this->patchJson("/api/recurring/{$recurring->id}", ['active' => null]);
        $untouched->assertOk();
        $untouched->assertJsonPath('data.active', false);

        $on = $this->patchJson("/api/recurring/{$recurring->id}", ['active' => true]);
        $on->assertOk();
        $on->assertJsonPath('data.active', true);
    }

    public function test_patch_with_an_explicit_null_note_clears_it(): void
    {
        $recurring = Recurring::factory()->create(['note' => 'Apartment rent']);

        $response = $this->patchJson("/api/recurring/{$recurring->id}", ['note' => null]);

        $response->assertOk();
        $response->assertJsonPath('data.note', null);
        $this->assertNull($recurring->fresh()->note);
    }

    public function test_patch_cross_field_validation_runs_against_the_stored_end_date(): void
    {
        $recurring = Recurring::factory()->create(['start_date' => '2026-01-01', 'end_date' => '2026-12-01']);

        $response = $this->patchJson("/api/recurring/{$recurring->id}", ['start_date' => '2027-01-01']);

        $response->assertStatus(422);
        $this->assertSame('The end date must be on or after the start date.', $response->json('errors.end_date.0'));
    }

    public function test_patch_cross_field_validation_runs_against_the_stored_start_date(): void
    {
        $recurring = Recurring::factory()->create(['start_date' => '2026-01-01', 'end_date' => '2026-12-01']);

        $response = $this->patchJson("/api/recurring/{$recurring->id}", ['end_date' => '2025-06-01']);

        $response->assertStatus(422);
        $this->assertSame('The end date must be on or after the start date.', $response->json('errors.end_date.0'));
    }

    public function test_patch_with_a_null_end_date_reopens_the_template(): void
    {
        $recurring = Recurring::factory()->create(['start_date' => '2026-01-01', 'end_date' => '2026-12-01']);

        $response = $this->patchJson("/api/recurring/{$recurring->id}", ['end_date' => null]);

        $response->assertOk();
        $response->assertJsonPath('data.end_date', null);
        $this->assertNull($recurring->fresh()->end_date);
    }

    public function test_patch_of_a_missing_id_404s_before_validating_the_body(): void
    {
        $response = $this->patchJson('/api/recurring/999', ['day_of_month' => 99]);

        $response->assertNotFound();
        $this->assertSame('That standing order is gone.', $response->json('message'));
        $this->assertArrayNotHasKey('errors', $response->json());
    }

    // Raw column reads, not model reads — the cast would launder a dirty value. Beyond
    // serialization, materialization compares these as strings ('2026-09-02 00:00:00'
    // sorts after '2026-09-02'), so a template starting today would never fire.
    public function test_store_writes_both_date_columns_as_bare_calendar_dates(): void
    {
        $category = Category::factory()->create();

        $response = $this->postJson('/api/recurring', [
            'amount' => 1500000,
            'category_id' => $category->id,
            'day_of_month' => 1,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-01',
        ]);

        $response->assertCreated();
        $stored = DB::table('recurring')->where('id', $response->json('data.id'))->first();
        $this->assertSame('2026-01-01', $stored->start_date);
        $this->assertSame('2026-12-01', $stored->end_date);
    }

    public function test_patching_the_dates_keeps_the_columns_bare_calendar_dates(): void
    {
        $recurring = Recurring::factory()->create(['start_date' => '2026-01-01', 'end_date' => null]);

        $response = $this->patchJson("/api/recurring/{$recurring->id}", [
            'start_date' => '2026-02-01',
            'end_date' => '2026-11-30',
        ]);

        $response->assertOk();
        $stored = DB::table('recurring')->where('id', $recurring->id)->first();
        $this->assertSame('2026-02-01', $stored->start_date);
        $this->assertSame('2026-11-30', $stored->end_date);
    }

    public function test_destroy_removes_only_the_template_and_leaves_materialized_expenses_stamped(): void
    {
        $category = Category::factory()->create();
        $recurring = Recurring::factory()->create(['category_id' => $category->id]);

        $materialized = Expense::factory()->count(3)->create([
            'category_id' => $category->id,
            'recurring_id' => $recurring->id,
        ]);

        $response = $this->deleteJson("/api/recurring/{$recurring->id}");

        $response->assertNoContent();
        $this->assertSame('', $response->getContent());
        $this->assertSoftDeleted('recurring', ['id' => $recurring->id]);
        $this->getJson("/api/recurring/{$recurring->id}")->assertNotFound();

        // The contract invariant: the stamp survives its template — not nulled, not cascaded.
        foreach ($materialized as $expense) {
            $this->assertDatabaseHas('expenses', [
                'id' => $expense->id,
                'recurring_id' => $recurring->id,
            ]);
            $this->assertSame($recurring->id, $expense->fresh()->recurring_id);
        }

        $this->getJson('/api/expenses')->assertOk()->assertJsonPath('meta.total', 3);
    }

    public function test_timestamps_are_iso_8601_utc_with_no_fractional_seconds(): void
    {
        $recurring = Recurring::factory()->create();

        $data = $this->getJson("/api/recurring/{$recurring->id}")->json('data');

        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $data['created_at']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $data['updated_at']);
    }

    public function test_restore_brings_back_the_same_template(): void
    {
        $recurring = Recurring::factory()->create();
        $this->deleteJson("/api/recurring/{$recurring->id}")->assertNoContent();

        $response = $this->postJson("/api/recurring/{$recurring->id}/restore");

        $response->assertOk();
        $this->assertSame(self::SHAPE, array_keys($response->json('data')));
        $this->assertSame($recurring->id, $response->json('data.id'));
        $this->assertCount(1, $this->getJson('/api/recurring')->json('data'));
    }

    public function test_restore_of_a_live_template_404s(): void
    {
        $recurring = Recurring::factory()->create();

        $this->postJson("/api/recurring/{$recurring->id}/restore")
            ->assertNotFound()
            ->assertJsonPath('message', 'That standing order is gone.');
    }
}
