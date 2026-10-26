<?php

namespace Tests\Feature;

use App\Actions\MaterializeRecurring;
use App\Models\Category;
use App\Models\Expense;
use App\Models\User;
use App\Support\LedgerCipher;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\AllowsUnownedRows;
use Tests\TestCase;

class ClaimDataTest extends TestCase
{
    use AllowsUnownedRows;
    use DatabaseMigrations;

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

    public function test_claim_takes_a_voided_standing_order_too(): void
    {
        $owner = User::factory()->create(['email' => 'owner@example.com']);
        $order = $this->unownedOrder($this->unownedCategory('Housing'));
        DB::table('recurring')->where('id', $order)->update(['deleted_at' => self::STAMP]);

        $this->artisan('pesonal:claim', ['email' => 'owner@example.com'])
            ->expectsOutput('Claimed 1 categories, 1 standing orders, 0 expenses for owner@example.com.')
            ->assertSuccessful();

        $this->assertSame($owner->id, DB::table('recurring')->where('id', $order)->value('user_id'));
    }

    public function test_claim_keeps_every_rows_timestamps(): void
    {
        $owner = User::factory()->create(['email' => 'owner@example.com']);
        DB::table('categories')->insert([
            'name' => 'Food', 'color' => '#C9692E', 'emoji' => '🍔', 'user_id' => $owner->id, 'created_at' => self::STAMP, 'updated_at' => self::STAMP,
        ]);

        // updated_at reads as "paused since" on an order and "last changed" on an expense.
        $housing = $this->unownedCategory('Housing');
        $paused = $this->unownedOrder($housing);
        DB::table('recurring')->where('id', $paused)->update(['active' => 0, 'updated_at' => '2026-05-01 08:00:00']);
        $voidedOrder = $this->unownedOrder($housing);
        DB::table('recurring')->where('id', $voidedOrder)->update(['updated_at' => '2026-04-01 08:00:00', 'deleted_at' => '2026-04-01 08:00:00']);
        $expense = $this->unownedExpense($housing);
        DB::table('expenses')->where('id', $expense)->update(['updated_at' => '2026-02-01 08:00:00']);

        // Rows the category-name merge re-points.
        $unownedFood = $this->unownedCategory('Food');
        $mergedOrder = $this->unownedOrder($unownedFood);
        DB::table('recurring')->where('id', $mergedOrder)->update(['updated_at' => '2026-03-01 08:00:00']);
        $mergedExpense = $this->unownedExpense($unownedFood);
        DB::table('expenses')->where('id', $mergedExpense)->update(['updated_at' => '2026-03-02 08:00:00']);

        $stamps = fn (string $table) => DB::table($table)->orderBy('id')->get(['id', 'created_at', 'updated_at'])->keyBy('id')->all();
        $before = [
            // The merged duplicate is deleted, so it has nothing to compare.
            'categories' => collect($stamps('categories'))->except($unownedFood)->all(),
            'recurring' => $stamps('recurring'),
            'expenses' => $stamps('expenses'),
        ];

        $this->artisan('pesonal:claim', ['email' => 'owner@example.com'])->assertSuccessful();

        $this->assertSame(0, DB::table('recurring')->whereNull('user_id')->count());
        $this->assertSame(0, DB::table('expenses')->whereNull('user_id')->count());
        foreach ($before as $table => $rows) {
            $this->assertEquals($rows, $stamps($table), "{$table} timestamps changed");
        }
    }

    public function test_claim_still_works_on_a_plaintext_backup(): void
    {
        // setUp rolled back to the plaintext schema: no encryption, no fingerprints.
        $this->assertFalse(Schema::hasColumn('categories', 'name_index'));

        $owner = User::factory()->create(['email' => 'owner@example.com']);
        $ownersFood = DB::table('categories')->insertGetId([
            'name' => 'Food', 'color' => '#C9692E', 'emoji' => '🍔', 'user_id' => $owner->id, 'created_at' => self::STAMP, 'updated_at' => self::STAMP,
        ]);
        $unownedFood = $this->unownedCategory('Food');
        $transport = $this->unownedCategory('Transport');
        $order = $this->unownedOrder($unownedFood);
        $lunch = $this->unownedExpense($unownedFood, $order);
        $voided = $this->unownedExpense($transport, null, self::STAMP);

        $this->artisan('pesonal:claim', ['email' => 'owner@example.com'])
            ->expectsOutput('Merged 1 categories into ones the account already had.')
            ->expectsOutput('Claimed 1 categories, 1 standing orders, 2 expenses for owner@example.com.')
            ->assertSuccessful();

        // Then the deploy's migrate: require_user_id, then the encryption.
        $this->artisan('migrate')->assertSuccessful();

        $this->assertNull(DB::table('categories')->where('id', $unownedFood)->first());
        foreach (['categories' => [$ownersFood, $transport], 'recurring' => [$order], 'expenses' => [$lunch, $voided]] as $table => $ids) {
            foreach ($ids as $id) {
                $this->assertSame($owner->id, DB::table($table)->where('id', $id)->value('user_id'), "{$table} row {$id}");
            }
        }

        $raw = fn (string $table, int $id) => DB::table($table)->where('id', $id)->sole();
        foreach ([$lunch, $voided] as $id) {
            $this->assertTrue(LedgerCipher::isEncrypted((string) $raw('expenses', $id)->amount));
            $this->assertTrue(LedgerCipher::isEncrypted((string) $raw('expenses', $id)->note));
        }
        $this->assertSame(1500000, LedgerCipher::decryptAmount((string) $raw('recurring', $order)->amount));
        $this->assertSame('Rent', LedgerCipher::decrypt((string) $raw('recurring', $order)->note));
        $this->assertSame($ownersFood, $raw('recurring', $order)->category_id);
        $this->assertSame($ownersFood, $raw('expenses', $lunch)->category_id);

        foreach ([$ownersFood => 'Food', $transport => 'Transport'] as $id => $name) {
            $this->assertTrue(LedgerCipher::isEncrypted((string) $raw('categories', $id)->name));
            $this->assertSame($name, LedgerCipher::decrypt((string) $raw('categories', $id)->name));
            $this->assertSame(LedgerCipher::fingerprint($owner->id, $name), $raw('categories', $id)->name_index);
        }

        // And the app reads it all back for the owner.
        $this->actingAs($owner);
        $this->assertSame(15000, Expense::query()->findOrFail($lunch)->amount->centavos());
        $this->assertSame('Kape', Expense::onlyTrashed()->findOrFail($voided)->note);
        $this->assertSame(['Food', 'Transport'], Category::query()->get()->pluck('name')->sort()->values()->all());
    }

    public function test_claim_merges_a_name_that_differs_only_by_spaces(): void
    {
        $owner = User::factory()->create(['email' => 'owner@example.com']);
        $ownersFood = DB::table('categories')->insertGetId([
            'name' => 'Food', 'color' => '#C9692E', 'emoji' => '🍔', 'user_id' => $owner->id, 'created_at' => self::STAMP, 'updated_at' => self::STAMP,
        ]);
        $spaced = $this->unownedCategory(' Food ');
        $lunch = $this->unownedExpense($spaced);

        $this->artisan('pesonal:claim', ['email' => 'owner@example.com'])
            ->expectsOutput('Merged 1 categories into ones the account already had.')
            ->assertSuccessful();

        $this->assertNull(DB::table('categories')->where('id', $spaced)->first());
        $this->assertSame($ownersFood, DB::table('expenses')->where('id', $lunch)->value('category_id'));

        // The fingerprint trims, so an unmerged " Food " would break this unique index.
        $this->artisan('migrate')->assertSuccessful();
        $this->assertSame(LedgerCipher::fingerprint($owner->id, 'Food'), DB::table('categories')->where('id', $ownersFood)->value('name_index'));
    }

    public function test_the_job_skips_an_unclaimed_order_even_with_someone_signed_in(): void
    {
        $this->actingAs(User::factory()->create());

        // Rows from before accounts existed wait for pesonal:claim; the signed-in
        // user must not pick up their charges through the creating stamp.
        $categoryId = DB::table('categories')->insertGetId([
            'name' => 'Housing', 'color' => '#534F8C', 'emoji' => '🏠', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('recurring')->insert([
            'amount' => 1500000, 'note' => 'Rent', 'day_of_month' => 1, 'start_date' => '2026-01-01', 'end_date' => null,
            'active' => 1, 'category_id' => $categoryId, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->assertNotNull(auth()->id());

        $this->assertSame(0, (new MaterializeRecurring)->execute(Carbon::create(2026, 6, 15)));
        $this->assertSame(0, DB::table('expenses')->count());
    }
}
