<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\ActsAsUser;
use Tests\TestCase;

class ExpenseItemsTest extends TestCase
{
    use ActsAsUser;
    use RefreshDatabase;

    private const OVER = 'The items add up to more than the amount.';

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function payload(array $extra = []): array
    {
        return array_merge([
            'amount' => 5500,
            'date' => '2026-06-17',
            'category_id' => Category::factory()->create()->id,
        ], $extra);
    }

    /** @return list<array{name: string, amount: int}> */
    private function stored(): array
    {
        return [['name' => 'Rice', 'amount' => 4500], ['name' => 'Adobo', 'amount' => 1000]];
    }

    public function test_create_with_items_returns_them_in_order(): void
    {
        $response = $this->postJson('/api/expenses', $this->payload(['items' => $this->stored()]));

        $response->assertCreated();
        $this->assertSame($this->stored(), $response->json('data.items'));
        $this->assertSame($this->stored(), Expense::first()->items);
    }

    public function test_create_without_items_reads_back_an_empty_list(): void
    {
        $response = $this->postJson('/api/expenses', $this->payload());

        $response->assertCreated();
        $this->assertSame([], $response->json('data.items'));
    }

    public function test_items_may_add_up_to_exactly_the_amount(): void
    {
        $this->postJson('/api/expenses', $this->payload(['items' => $this->stored()]))->assertCreated();
    }

    public function test_items_over_the_amount_are_refused(): void
    {
        $items = [...$this->stored(), ['name' => 'Soda', 'amount' => 1000]];

        $response = $this->postJson('/api/expenses', $this->payload(['items' => $items]));

        $response->assertStatus(422);
        $this->assertSame(self::OVER, $response->json('errors.items.0'));
        $this->assertSame(0, Expense::count());
    }

    public function test_patch_lowering_amount_below_stored_items_is_refused(): void
    {
        $expense = Expense::factory()->create(['amount' => 5500, 'items' => $this->stored()]);

        $response = $this->patchJson("/api/expenses/{$expense->id}", ['amount' => 5000]);

        $response->assertStatus(422);
        $this->assertSame(self::OVER, $response->json('errors.items.0'));
        $this->assertSame(5500, $expense->fresh()->amount->centavos());
    }

    public function test_patch_absent_items_leaves_them_unchanged(): void
    {
        $expense = Expense::factory()->create(['amount' => 5500, 'items' => $this->stored()]);

        $this->patchJson("/api/expenses/{$expense->id}", ['amount' => 6000])->assertOk();

        $this->assertSame($this->stored(), $expense->fresh()->items);
    }

    public function test_patch_empty_items_clears_them(): void
    {
        $expense = Expense::factory()->create(['amount' => 5500, 'items' => $this->stored()]);

        $response = $this->patchJson("/api/expenses/{$expense->id}", ['items' => []]);

        $response->assertOk();
        $this->assertSame([], $response->json('data.items'));
        $this->assertNull(DB::table('expenses')->where('id', $expense->id)->value('items'));
    }

    public function test_patch_items_replaces_the_whole_list(): void
    {
        $expense = Expense::factory()->create(['amount' => 5500, 'items' => $this->stored()]);
        $new = [['name' => 'Coffee', 'amount' => 200]];

        $this->patchJson("/api/expenses/{$expense->id}", ['items' => $new])->assertOk();

        $this->assertSame($new, $expense->fresh()->items);
    }

    public function test_patch_without_amount_or_items_ignores_an_over_total_record(): void
    {
        $expense = Expense::factory()->create(['amount' => 5000]);
        $expense->items = [['name' => 'Big', 'amount' => 6000]];
        $expense->save();

        $this->patchJson("/api/expenses/{$expense->id}", ['date' => '2026-06-20'])->assertOk();

        $this->assertSame([['name' => 'Big', 'amount' => 6000]], $expense->fresh()->items);
    }

    /** @return array<string, array{mixed, string}> */
    public static function badItems(): array
    {
        $ok = ['name' => 'Rice', 'amount' => 100];

        return [
            'name missing' => [[['amount' => 100]], 'items.0.name'],
            'name blank' => [[['name' => '   ', 'amount' => 100]], 'items.0.name'],
            'name too long' => [[['name' => str_repeat('a', 41), 'amount' => 100]], 'items.0.name'],
            'amount zero' => [[['name' => 'Rice', 'amount' => 0]], 'items.0.amount'],
            'amount negative' => [[['name' => 'Rice', 'amount' => -5]], 'items.0.amount'],
            'amount string' => [[['name' => 'Rice', 'amount' => '150']], 'items.0.amount'],
            'amount float' => [[['name' => 'Rice', 'amount' => 1.5]], 'items.0.amount'],
            'amount missing' => [[['name' => 'Rice']], 'items.0.amount'],
            'too many' => [array_fill(0, 21, $ok), 'items'],
            'not an array' => ['rice', 'items'],
            'element not an object' => [['rice'], 'items.0'],
        ];
    }

    #[DataProvider('badItems')]
    public function test_item_rules(mixed $items, string $key): void
    {
        $response = $this->postJson('/api/expenses', $this->payload(['amount' => 100000, 'items' => $items]));

        $response->assertStatus(422);
        $this->assertArrayHasKey($key, $response->json('errors'));
        $this->assertSame(0, Expense::count());
    }

    public function test_a_forty_char_name_with_spaces_passes_and_is_stored_trimmed(): void
    {
        $name = str_repeat('a', 40);

        $response = $this->postJson('/api/expenses', $this->payload(['items' => [['name' => "  $name  ", 'amount' => 100, 'extra' => 'x']]]));

        $response->assertCreated();
        $this->assertSame([['name' => $name, 'amount' => 100]], $response->json('data.items'));
    }

    public function test_another_users_expense_is_still_404_with_items(): void
    {
        $expense = Expense::factory()->create(['amount' => 5500, 'items' => $this->stored()]);
        $this->actingAs(User::factory()->create(), 'sanctum');

        $this->patchJson("/api/expenses/{$expense->id}", ['items' => []])->assertNotFound();
    }

    public function test_restoring_a_voided_expense_keeps_its_items(): void
    {
        $expense = Expense::factory()->create(['amount' => 5500, 'items' => $this->stored()]);
        $this->deleteJson("/api/expenses/{$expense->id}")->assertNoContent();

        $response = $this->postJson("/api/expenses/{$expense->id}/restore");

        $response->assertOk();
        $this->assertSame($this->stored(), $response->json('data.items'));
        $this->assertSame($this->stored(), Expense::find($expense->id)->items);
    }
}
