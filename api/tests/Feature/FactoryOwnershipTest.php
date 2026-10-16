<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\Recurring;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FactoryOwnershipTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{class-string<Expense|Recurring>}> */
    public static function models(): array
    {
        return ['expense' => [Expense::class], 'standing order' => [Recurring::class]];
    }

    /** @param  class-string<Expense|Recurring>  $model */
    #[DataProvider('models')]
    public function test_a_row_made_with_nobody_signed_in_shares_its_categorys_owner(string $model): void
    {
        $row = $model::factory()->create();

        $this->assertSame($row->user_id, $this->categoryOwner($row->category_id));
    }

    /** @param  class-string<Expense|Recurring>  $model */
    #[DataProvider('models')]
    public function test_a_row_given_an_owner_gets_a_category_with_that_owner(string $model): void
    {
        $user = User::factory()->create();

        $row = $model::factory()->create(['user_id' => $user->id]);

        $this->assertSame($user->id, $row->user_id);
        $this->assertSame($user->id, $this->categoryOwner($row->category_id));
    }

    // The raw table: the model's ownership scope hides every row while nobody is signed in.
    private function categoryOwner(int $categoryId): int
    {
        return (int) DB::table('categories')->where('id', $categoryId)->value('user_id');
    }
}
