<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Recurring;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\ActsAsUser;
use Tests\TestCase;

class RecurringItemsTest extends TestCase
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
            'day_of_month' => 5, 'start_date' => '2026-06-01',
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
        $response = $this->postJson('/api/recurring', $this->payload(['items' => $this->stored()]));

        $response->assertCreated();
        $this->assertSame($this->stored(), $response->json('data.items'));
        $this->assertSame($this->stored(), Recurring::first()->items);
    }

    public function test_create_without_items_reads_back_an_empty_list(): void
    {
        $response = $this->postJson('/api/recurring', $this->payload());

        $response->assertCreated();
        $this->assertSame([], $response->json('data.items'));
    }

    public function test_items_may_add_up_to_exactly_the_amount(): void
    {
        $this->postJson('/api/recurring', $this->payload(['items' => $this->stored()]))->assertCreated();
    }

    public function test_items_over_the_amount_are_refused(): void
    {
        $items = [...$this->stored(), ['name' => 'Soda', 'amount' => 1000]];

        $response = $this->postJson('/api/recurring', $this->payload(['items' => $items]));

        $response->assertStatus(422);
        $this->assertSame(self::OVER, $response->json('errors.items.0'));
        $this->assertSame(0, Recurring::count());
    }

    public function test_patch_lowering_amount_below_stored_items_is_refused(): void
    {
        $recurring = Recurring::factory()->create(['amount' => 5500, 'items' => $this->stored()]);

        $response = $this->patchJson("/api/recurring/{$recurring->id}", ['amount' => 5000]);

        $response->assertStatus(422);
        $this->assertSame(self::OVER, $response->json('errors.items.0'));
        $this->assertSame(5500, $recurring->fresh()->amount->centavos());
    }

    public function test_patch_absent_items_leaves_them_unchanged(): void
    {
        $recurring = Recurring::factory()->create(['amount' => 5500, 'items' => $this->stored()]);

        $this->patchJson("/api/recurring/{$recurring->id}", ['amount' => 6000])->assertOk();

        $this->assertSame($this->stored(), $recurring->fresh()->items);
    }

    public function test_patch_empty_items_clears_them(): void
    {
        $recurring = Recurring::factory()->create(['amount' => 5500, 'items' => $this->stored()]);

        $response = $this->patchJson("/api/recurring/{$recurring->id}", ['items' => []]);

        $response->assertOk();
        $this->assertSame([], $response->json('data.items'));
        $this->assertNull(DB::table('recurring')->where('id', $recurring->id)->value('items'));
    }

    public function test_patch_items_replaces_the_whole_list(): void
    {
        $recurring = Recurring::factory()->create(['amount' => 5500, 'items' => $this->stored()]);
        $new = [['name' => 'Coffee', 'amount' => 200]];

        $this->patchJson("/api/recurring/{$recurring->id}", ['items' => $new])->assertOk();

        $this->assertSame($new, $recurring->fresh()->items);
    }

    public function test_patch_without_amount_or_items_ignores_an_over_total_record(): void
    {
        $recurring = Recurring::factory()->create(['amount' => 5000]);
        $recurring->items = [['name' => 'Big', 'amount' => 6000]];
        $recurring->save();

        $this->patchJson("/api/recurring/{$recurring->id}", ['active' => false])->assertOk();

        $this->assertSame([['name' => 'Big', 'amount' => 6000]], $recurring->fresh()->items);
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
        $response = $this->postJson('/api/recurring', $this->payload(['amount' => 100000, 'items' => $items]));

        $response->assertStatus(422);
        $this->assertArrayHasKey($key, $response->json('errors'));
        $this->assertSame(0, Recurring::count());
    }

    public function test_a_forty_char_name_with_spaces_passes_and_is_stored_trimmed(): void
    {
        $name = str_repeat('a', 40);

        $response = $this->postJson('/api/recurring', $this->payload(['items' => [['name' => "  $name  ", 'amount' => 100, 'extra' => 'x']]]));

        $response->assertCreated();
        $this->assertSame([['name' => $name, 'amount' => 100]], $response->json('data.items'));
    }

    public function test_another_users_standing_order_is_still_404_with_items(): void
    {
        $recurring = Recurring::factory()->create(['amount' => 5500, 'items' => $this->stored()]);
        $this->actingAs(User::factory()->create(), 'sanctum');

        $this->patchJson("/api/recurring/{$recurring->id}", ['items' => []])->assertNotFound();
    }
}
