<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ClaimDataTest extends TestCase
{
    use RefreshDatabase;

    private const STAMP = '2026-01-01 00:00:00';

    /** Rows from before accounts existed: no user_id at all. */
    private function unownedCategory(string $name): int
    {
        return DB::table('categories')->insertGetId([
            'name' => $name, 'color' => '#C9692E', 'emoji' => '🍔', 'created_at' => self::STAMP, 'updated_at' => self::STAMP,
        ]);
    }

    private function unownedOrder(int $categoryId): int
    {
        return DB::table('recurring')->insertGetId([
            'amount' => 1500000, 'note' => 'Rent', 'day_of_month' => 1, 'start_date' => '2026-01-01', 'end_date' => null,
            'active' => 1, 'category_id' => $categoryId, 'created_at' => self::STAMP, 'updated_at' => self::STAMP,
        ]);
    }

    private function unownedExpense(int $categoryId, ?int $orderId = null, ?string $deletedAt = null): int
    {
        return DB::table('expenses')->insertGetId([
            'amount' => 15000, 'date' => '2026-06-01', 'note' => 'Kape', 'category_id' => $categoryId, 'recurring_id' => $orderId,
            'created_at' => self::STAMP, 'updated_at' => self::STAMP, 'deleted_at' => $deletedAt,
        ]);
    }

    public function test_claim_assigns_unowned_rows_to_the_account(): void
    {
        $owner = User::factory()->create(['email' => 'owner@example.com']);
        $other = User::factory()->create();

        $food = $this->unownedCategory('Food');
        $fun = $this->unownedCategory('Fun');
        $order = $this->unownedOrder($food);
        $this->unownedExpense($food, $order);
        $this->unownedExpense($fun);
        $this->unownedExpense($fun, null, self::STAMP); // voided rows are claimed too

        // Someone else's row is never taken.
        $othersCategory = DB::table('categories')->insertGetId([
            'name' => 'Theirs', 'color' => '#C9692E', 'emoji' => '🍔', 'user_id' => $other->id, 'created_at' => self::STAMP, 'updated_at' => self::STAMP,
        ]);

        $this->artisan('pesonal:claim', ['email' => 'Owner@Example.com'])
            ->expectsOutput('Claimed 2 categories, 1 standing orders, 3 expenses for owner@example.com.')
            ->assertSuccessful();

        $this->assertSame(0, DB::table('categories')->whereNull('user_id')->count());
        $this->assertSame(0, DB::table('recurring')->whereNull('user_id')->count());
        $this->assertSame(0, DB::table('expenses')->whereNull('user_id')->count());
        $this->assertSame(2, DB::table('categories')->where('user_id', $owner->id)->count());
        $this->assertSame(1, DB::table('recurring')->where('user_id', $owner->id)->count());
        $this->assertSame(3, DB::table('expenses')->where('user_id', $owner->id)->count());
        $this->assertSame($other->id, DB::table('categories')->where('id', $othersCategory)->value('user_id'));
    }

    public function test_claim_refuses_an_unknown_email(): void
    {
        $food = $this->unownedCategory('Food');

        $this->artisan('pesonal:claim', ['email' => 'nobody@example.com'])->assertExitCode(1);

        $this->assertNull(DB::table('categories')->where('id', $food)->value('user_id'));
    }

    public function test_claim_merges_a_category_the_account_already_has(): void
    {
        $owner = User::factory()->create(['email' => 'owner@example.com']);
        $ownersFood = DB::table('categories')->insertGetId([
            'name' => 'Food', 'color' => '#C9692E', 'emoji' => '🍔', 'user_id' => $owner->id, 'created_at' => self::STAMP, 'updated_at' => self::STAMP,
        ]);

        $unownedFood = $this->unownedCategory('Food');
        $transport = $this->unownedCategory('Transport');
        $order = $this->unownedOrder($unownedFood);
        $lunch = $this->unownedExpense($unownedFood, $order);
        $voided = $this->unownedExpense($unownedFood, null, self::STAMP);
        $fare = $this->unownedExpense($transport);

        $this->artisan('pesonal:claim', ['email' => 'owner@example.com'])
            ->expectsOutput('Merged 1 categories into ones the account already had.')
            ->expectsOutput('Claimed 1 categories, 1 standing orders, 3 expenses for owner@example.com.')
            ->assertSuccessful();

        // The duplicate is gone and everything that pointed at it now points at the account's own.
        $this->assertNull(DB::table('categories')->where('id', $unownedFood)->first());
        $this->assertSame($ownersFood, DB::table('recurring')->where('id', $order)->value('category_id'));
        $this->assertSame($ownersFood, DB::table('expenses')->where('id', $lunch)->value('category_id'));
        $this->assertSame($ownersFood, DB::table('expenses')->where('id', $voided)->value('category_id'));
        $this->assertSame($transport, DB::table('expenses')->where('id', $fare)->value('category_id'));

        $this->assertSame(['Food', 'Transport'], DB::table('categories')->where('user_id', $owner->id)->orderBy('name')->pluck('name')->all());
        $this->assertSame(3, DB::table('expenses')->where('user_id', $owner->id)->count());
    }
}
