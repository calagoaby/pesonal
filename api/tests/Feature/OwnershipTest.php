<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Expense;
use App\Models\Recurring;
use App\Models\Scopes\OwnedByCurrentUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OwnershipTest extends TestCase
{
    use RefreshDatabase;

    private User $a;

    private User $b;

    protected function setUp(): void
    {
        parent::setUp();

        $this->a = User::factory()->create();
        $this->b = User::factory()->create();
    }

    /** @return array{category: Category, expense: Expense, recurring: Recurring, voidedExpense: Expense, voidedRecurring: Recurring} */
    private function rowsOfA(): array
    {
        Sanctum::actingAs($this->a);

        $category = Category::factory()->create(['name' => 'Food']);
        $expense = Expense::factory()->create(['category_id' => $category->id, 'date' => '2026-06-10', 'amount' => 15000]);
        $recurring = Recurring::factory()->create(['category_id' => $category->id]);
        $voidedExpense = Expense::factory()->create(['category_id' => $category->id]);
        $voidedExpense->delete();
        $voidedRecurring = Recurring::factory()->create(['category_id' => $category->id]);
        $voidedRecurring->delete();

        return compact('category', 'expense', 'recurring', 'voidedExpense', 'voidedRecurring');
    }

    public function test_b_gets_404_on_each_of_as_rows(): void
    {
        $rows = $this->rowsOfA();
        Sanctum::actingAs($this->b);

        $routes = [
            "/api/categories/{$rows['category']->id}" => 'That category is gone.',
            "/api/expenses/{$rows['expense']->id}" => 'That entry is not on the roll.',
            "/api/recurring/{$rows['recurring']->id}" => 'That standing order is gone.',
        ];

        foreach ($routes as $url => $message) {
            $this->getJson($url)->assertNotFound()->assertJsonPath('message', $message);
            $this->patchJson($url, ['note' => 'mine now'])->assertNotFound()->assertJsonPath('message', $message);
            $this->deleteJson($url)->assertNotFound()->assertJsonPath('message', $message);
        }

        $this->postJson("/api/expenses/{$rows['voidedExpense']->id}/restore")
            ->assertNotFound()
            ->assertJsonPath('message', 'That entry is not on the roll.');
        $this->postJson("/api/recurring/{$rows['voidedRecurring']->id}/restore")
            ->assertNotFound()
            ->assertJsonPath('message', 'That standing order is gone.');

        // Nothing of A's was touched by any of those calls.
        $this->assertNotNull(Category::withoutGlobalScope(OwnedByCurrentUser::class)->find($rows['category']->id));
        $this->assertNotNull(Expense::withoutGlobalScope(OwnedByCurrentUser::class)->find($rows['expense']->id));
        $this->assertNotNull(Recurring::withoutGlobalScope(OwnedByCurrentUser::class)->find($rows['recurring']->id));
        $this->assertTrue(Expense::withoutGlobalScope(OwnedByCurrentUser::class)->withTrashed()->find($rows['voidedExpense']->id)->trashed());
    }

    public function test_lists_only_contain_the_callers_rows(): void
    {
        $this->rowsOfA();
        Sanctum::actingAs($this->b);

        $mine = Category::factory()->create(['name' => 'Fun']);
        $myExpense = Expense::factory()->create(['category_id' => $mine->id]);
        $myOrder = Recurring::factory()->create(['category_id' => $mine->id]);

        $this->getJson('/api/categories')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $mine->id);
        $this->getJson('/api/expenses')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $myExpense->id)
            ->assertJsonPath('meta.total', 1);
        $this->getJson('/api/recurring')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $myOrder->id);
    }

    public function test_stats_only_sum_the_callers_rows(): void
    {
        $this->travelTo('2026-06-20');
        $this->rowsOfA();
        Sanctum::actingAs($this->b);

        $mine = Category::factory()->create(['name' => 'Fun']);
        Expense::factory()->create(['category_id' => $mine->id, 'date' => '2026-06-05', 'amount' => 2000]);

        $monthly = $this->getJson('/api/stats/monthly?month=2026-06')->assertOk();
        $this->assertSame(2000, $monthly->json('data.total'));
        $this->assertCount(1, $monthly->json('data.by_category'));
        $this->assertSame($mine->id, $monthly->json('data.by_category.0.category.id'));

        $trend = $this->getJson('/api/stats/trend?months=1')->assertOk();
        $this->assertSame([['month' => '2026-06', 'total' => 2000]], $trend->json('data'));
    }

    public function test_b_cannot_use_as_category_id(): void
    {
        $rows = $this->rowsOfA();
        Sanctum::actingAs($this->b);

        $this->postJson('/api/expenses', ['amount' => 1000, 'date' => '2026-06-01', 'category_id' => $rows['category']->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('category_id');

        $this->postJson('/api/recurring', ['amount' => 1000, 'category_id' => $rows['category']->id, 'day_of_month' => 1, 'start_date' => '2026-06-01'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('category_id');

        // And not by moving one of B's own rows onto it either.
        $mine = Category::factory()->create(['name' => 'Fun']);
        $myExpense = Expense::factory()->create(['category_id' => $mine->id]);
        $this->patchJson("/api/expenses/{$myExpense->id}", ['category_id' => $rows['category']->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('category_id');
    }

    public function test_two_users_can_each_own_a_category_named_food(): void
    {
        $this->rowsOfA();
        Sanctum::actingAs($this->b);

        $this->postJson('/api/categories', ['name' => 'Food', 'color' => '#C9692E', 'emoji' => '🍔'])->assertCreated();

        // B's own duplicate is still refused, on create and on rename.
        $this->postJson('/api/categories', ['name' => 'Food', 'color' => '#C9692E', 'emoji' => '🍔'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
        $fun = Category::factory()->create(['name' => 'Fun']);
        $this->patchJson("/api/categories/{$fun->id}", ['name' => 'Food'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    public function test_category_delete_conflict_counts_only_the_callers_rows(): void
    {
        Sanctum::actingAs($this->b);
        $category = Category::factory()->create();
        Expense::factory()->create(['category_id' => $category->id]);

        // Rows of A's pointing at B's category can't come through the API; plant them directly.
        Expense::factory()->count(2)->create(['category_id' => $category->id, 'user_id' => $this->a->id]);

        $this->deleteJson("/api/categories/{$category->id}")
            ->assertStatus(409)
            ->assertExactJson(['message' => 'Still tags 1 expense. Move or delete those first, then it can go.']);
    }

    public function test_new_rows_are_stamped_with_the_caller(): void
    {
        Sanctum::actingAs($this->b);

        $categoryId = $this->postJson('/api/categories', ['name' => 'Food', 'color' => '#C9692E', 'emoji' => '🍔'])->json('data.id');
        $expenseId = $this->postJson('/api/expenses', ['amount' => 1000, 'date' => '2026-06-01', 'category_id' => $categoryId])->json('data.id');
        $orderId = $this->postJson('/api/recurring', ['amount' => 1000, 'category_id' => $categoryId, 'day_of_month' => 1, 'start_date' => '2026-06-01'])->json('data.id');

        $this->assertDatabaseHas('categories', ['id' => $categoryId, 'user_id' => $this->b->id]);
        $this->assertDatabaseHas('expenses', ['id' => $expenseId, 'user_id' => $this->b->id]);
        $this->assertDatabaseHas('recurring', ['id' => $orderId, 'user_id' => $this->b->id]);
    }

    public function test_with_nobody_signed_in_the_models_see_no_rows(): void
    {
        $this->rowsOfA();

        Auth::forgetGuards();
        $this->assertNull(auth()->id());

        $this->assertSame(0, Category::count());
        $this->assertSame(0, Expense::count());
        $this->assertSame(0, Recurring::count());
        $this->assertGreaterThan(0, Category::withoutGlobalScope(OwnedByCurrentUser::class)->count());
        $this->assertGreaterThan(0, Expense::withoutGlobalScope(OwnedByCurrentUser::class)->count());
        $this->assertGreaterThan(0, Recurring::withoutGlobalScope(OwnedByCurrentUser::class)->count());
    }

    public function test_data_routes_need_a_verified_signed_in_user(): void
    {
        $this->getJson('/api/categories')->assertUnauthorized();
        $this->getJson('/api/expenses')->assertUnauthorized();
        $this->getJson('/api/recurring')->assertUnauthorized();
        $this->getJson('/api/stats/monthly?month=2026-06')->assertUnauthorized();

        Sanctum::actingAs(User::factory()->unverified()->create());

        $this->getJson('/api/categories')
            ->assertForbidden()
            ->assertExactJson(['message' => 'Check your email — the receipt is not printed until you confirm it.']);
        $this->getJson('/api/stats/trend')->assertForbidden();
    }
}
