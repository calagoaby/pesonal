<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Expense;
use App\Models\Recurring;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsUser;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use ActsAsUser;
    use RefreshDatabase;

    private const SHAPE = ['id', 'name', 'color', 'emoji', 'created_at'];

    public function test_index_is_a_bare_data_envelope_with_no_meta(): void
    {
        Category::factory()->count(3)->create();

        $response = $this->getJson('/api/categories');

        $response->assertOk();
        $this->assertSame(['data'], array_keys($response->json()));
        $this->assertArrayNotHasKey('meta', $response->json());
    }

    public function test_index_is_ordered_by_id_ascending_and_each_row_has_exactly_five_keys(): void
    {
        $third = Category::factory()->create(['name' => 'Zulu']);
        $first = Category::factory()->create(['name' => 'Alpha']);

        $response = $this->getJson('/api/categories');

        $response->assertOk();
        $ids = array_column($response->json('data'), 'id');
        $this->assertSame([$third->id, $first->id], $ids); // id asc, not name order

        foreach ($response->json('data') as $row) {
            $this->assertSame(self::SHAPE, array_keys($row));
        }
    }

    public function test_show_returns_the_five_key_shape(): void
    {
        $category = Category::factory()->create(['name' => 'Food', 'color' => '#C9692E', 'emoji' => '🍔']);

        $response = $this->getJson("/api/categories/{$category->id}");

        $response->assertOk();
        $this->assertSame(['data'], array_keys($response->json()));
        $this->assertSame(self::SHAPE, array_keys($response->json('data')));
        $response->assertJsonPath('data.name', 'Food');
        $response->assertJsonPath('data.color', '#C9692E');
        $response->assertJsonPath('data.emoji', '🍔');
    }

    public function test_show_of_a_missing_id_404s_with_the_contract_message(): void
    {
        $response = $this->getJson('/api/categories/999');

        $response->assertNotFound();
        $this->assertSame('That category is gone.', $response->json('message'));
    }

    public function test_store_trims_the_name_and_persists_the_row(): void
    {
        $response = $this->postJson('/api/categories', [
            'name' => '  Coffee  ',
            'color' => '#C8472E',
            'emoji' => '☕',
        ]);

        $response->assertCreated();
        $this->assertSame(self::SHAPE, array_keys($response->json('data')));
        $response->assertJsonPath('data.name', 'Coffee');
        $this->assertDatabaseHas('categories', ['name' => 'Coffee', 'color' => '#C8472E', 'emoji' => '☕']);
    }

    public function test_store_rejects_a_whitespace_only_name_and_a_non_hex_color(): void
    {
        $response = $this->postJson('/api/categories', [
            'name' => '   ',
            'color' => 'blue',
            'emoji' => '☕',
        ]);

        $response->assertStatus(422);
        $this->assertSame('The given data was invalid.', $response->json('message'));
        $this->assertSame('The name field is required.', $response->json('errors.name.0'));
        $this->assertSame('The color must be a hex value.', $response->json('errors.color.0'));
    }

    public function test_store_requires_an_emoji(): void
    {
        $response = $this->postJson('/api/categories', [
            'name' => 'Coffee',
            'color' => '#C8472E',
        ]);

        $response->assertStatus(422);
        $this->assertSame('The emoji field is required.', $response->json('errors.emoji.0'));
    }

    public function test_store_rejects_a_duplicate_name(): void
    {
        Category::factory()->create(['name' => 'Food']);

        $response = $this->postJson('/api/categories', [
            'name' => 'Food',
            'color' => '#C9692E',
            'emoji' => '🍔',
        ]);

        $response->assertStatus(422);
        $this->assertSame('The name has already been taken.', $response->json('errors.name.0'));
    }

    public function test_update_with_only_a_color_leaves_the_other_fields_alone(): void
    {
        $category = Category::factory()->create(['name' => 'Food', 'color' => '#C9692E', 'emoji' => '🍔']);

        $response = $this->patchJson("/api/categories/{$category->id}", ['color' => '#534F8C']);

        $response->assertOk();
        $response->assertJsonPath('data.color', '#534F8C');
        $response->assertJsonPath('data.name', 'Food');
        $response->assertJsonPath('data.emoji', '🍔');
    }

    public function test_update_of_a_missing_id_404s_before_validating_the_body(): void
    {
        $response = $this->patchJson('/api/categories/999', ['color' => 'not-a-hex']);

        $response->assertNotFound();
        $this->assertSame('That category is gone.', $response->json('message'));
        $this->assertArrayNotHasKey('errors', $response->json());
    }

    public function test_update_unique_rule_rejects_another_name_but_ignores_its_own(): void
    {
        $food = Category::factory()->create(['name' => 'Food']);
        $fun = Category::factory()->create(['name' => 'Fun']);

        $clash = $this->patchJson("/api/categories/{$fun->id}", ['name' => 'Food']);
        $clash->assertStatus(422);
        $this->assertSame('The name has already been taken.', $clash->json('errors.name.0'));

        $self = $this->patchJson("/api/categories/{$food->id}", ['name' => 'Food']);
        $self->assertOk();
        $self->assertJsonPath('data.name', 'Food');
    }

    public function test_destroy_409s_with_a_singular_count_when_one_expense_references_it(): void
    {
        $category = Category::factory()->create();
        Expense::factory()->create(['category_id' => $category->id]);

        $response = $this->deleteJson("/api/categories/{$category->id}");

        $response->assertStatus(409);
        $this->assertSame(
            'Still tags 1 expense. Move or delete those first, then it can go.',
            $response->json('message')
        );
        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }

    public function test_destroy_409s_with_a_plural_count_when_three_expenses_reference_it(): void
    {
        $category = Category::factory()->create();
        Expense::factory()->count(3)->create(['category_id' => $category->id]);

        $response = $this->deleteJson("/api/categories/{$category->id}");

        $response->assertStatus(409);
        $this->assertSame(
            'Still tags 3 expenses. Move or delete those first, then it can go.',
            $response->json('message')
        );
    }

    public function test_destroy_409s_when_only_a_standing_order_uses_it(): void
    {
        $category = Category::factory()->create();
        Recurring::factory()->create(['category_id' => $category->id]);

        $response = $this->deleteJson("/api/categories/{$category->id}");

        $response->assertStatus(409);
        $this->assertSame(
            'Still tags 1 standing order. Move or delete those first, then it can go.',
            $response->json('message')
        );
        $this->assertArrayNotHasKey('errors', $response->json());
        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }

    public function test_destroy_409s_naming_both_counts_when_expenses_and_standing_orders_use_it(): void
    {
        $category = Category::factory()->create();
        Expense::factory()->count(2)->create(['category_id' => $category->id]);
        Recurring::factory()->count(3)->create(['category_id' => $category->id]);

        $response = $this->deleteJson("/api/categories/{$category->id}");

        $response->assertStatus(409);
        $this->assertSame(
            'Still tags 2 expenses and 3 standing orders. Move or delete those first, then it can go.',
            $response->json('message')
        );
    }

    public function test_destroy_an_unreferenced_category_returns_204_and_deletes_the_row(): void
    {
        $category = Category::factory()->create();

        $response = $this->deleteJson("/api/categories/{$category->id}");

        $response->assertNoContent();
        $this->assertSame('', $response->getContent());
        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    public function test_created_at_is_iso_8601_utc_with_no_fractional_seconds(): void
    {
        $category = Category::factory()->create();

        $createdAt = $this->getJson("/api/categories/{$category->id}")->json('data.created_at');

        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $createdAt);
    }

    public function test_destroy_succeeds_when_only_voided_rows_reference_it_and_takes_them_along(): void
    {
        $category = Category::factory()->create();
        $expense = Expense::factory()->create(['category_id' => $category->id]);
        $template = Recurring::factory()->create(['category_id' => $category->id]);
        $this->deleteJson("/api/expenses/{$expense->id}")->assertNoContent();
        $this->deleteJson("/api/recurring/{$template->id}")->assertNoContent();

        $this->deleteJson("/api/categories/{$category->id}")->assertNoContent();

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
        $this->assertDatabaseMissing('expenses', ['id' => $expense->id]);
        $this->assertDatabaseMissing('recurring', ['id' => $template->id]);
    }
}
