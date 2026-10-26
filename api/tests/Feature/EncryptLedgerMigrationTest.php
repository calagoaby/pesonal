<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Expense;
use App\Models\Recurring;
use App\Models\Scopes\OwnedByCurrentUser;
use App\Models\User;
use App\Support\LedgerCipher;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

// DatabaseMigrations, not RefreshDatabase: rolling a migration back and re-running it
// inside RefreshDatabase's wrapping transaction is unreliable.
class EncryptLedgerMigrationTest extends TestCase
{
    use DatabaseMigrations;

    private const MIGRATION = 'database/migrations/2026_10_26_000001_encrypt_ledger_fields.php';

    private const STAMP = '2026-01-01 00:00:00';

    private const EDITED = '2026-03-04 05:06:07';

    private const LEDGER = ['categories', 'recurring', 'expenses'];

    /** @var array<string, int> */
    private array $ids = [];

    private User $first;

    private User $second;

    // A fresh test database runs every migration in one batch, so a bare --step 1 would
    // undo whichever sorts last. --path narrows the last batch to this one file.
    private function rollBackTheMigration(): void
    {
        $this->artisan('migrate:rollback', ['--path' => self::MIGRATION])->assertSuccessful();
    }

    /** The plaintext ledger, as it was before encryption: two accounts, one voided entry. */
    private function insertPlainLedger(): void
    {
        $this->first = User::factory()->create();
        $this->second = User::factory()->create();

        $category = fn (User $user, string $name) => DB::table('categories')->insertGetId([
            'user_id' => $user->id, 'name' => $name, 'color' => '#C9692E', 'emoji' => '🍔', 'created_at' => self::STAMP, 'updated_at' => self::EDITED,
        ]);
        $this->ids['food1'] = $category($this->first, 'Food');
        $this->ids['food2'] = $category($this->second, 'Food');

        $this->ids['gym'] = DB::table('recurring')->insertGetId([
            'user_id' => $this->first->id, 'amount' => 50000, 'note' => 'Gym', 'day_of_month' => 1, 'start_date' => '2026-01-01',
            'end_date' => null, 'active' => 1, 'category_id' => $this->ids['food1'], 'created_at' => self::STAMP, 'updated_at' => self::EDITED,
        ]);

        $expense = fn (User $user, int $categoryId, int $amount, ?string $note, ?string $deletedAt = null) => DB::table('expenses')->insertGetId([
            'user_id' => $user->id, 'amount' => $amount, 'date' => '2026-06-01', 'note' => $note, 'category_id' => $categoryId,
            'recurring_id' => null, 'created_at' => self::STAMP, 'updated_at' => self::EDITED, 'deleted_at' => $deletedAt,
        ]);
        $this->ids['lunch'] = $expense($this->first, $this->ids['food1'], 15000, 'Lunch');
        $this->ids['silent'] = $expense($this->first, $this->ids['food1'], 15000, null);
        $this->ids['small'] = $expense($this->second, $this->ids['food2'], 999, 'Kape');
        $this->ids['voided'] = $expense($this->first, $this->ids['food1'], 2500, 'Oops', self::EDITED);
    }

    /** @return array<string, array<int, array<string, mixed>>> */
    private function snapshot(): array
    {
        return collect(self::LEDGER)->mapWithKeys(fn (string $table) => [
            $table => DB::table($table)->orderBy('id')->get()->map(fn (object $row) => (array) $row)->all(),
        ])->all();
    }

    /** @return array<int, array<string, int>> user id => [count, sum of amounts], counted with the models */
    private function perUserTotals(string $model): array
    {
        $totals = [];
        foreach ([$this->first, $this->second] as $user) {
            $rows = $model::withoutGlobalScope(OwnedByCurrentUser::class)->withTrashed()->where('user_id', $user->id)->get();
            $totals[$user->id] = ['count' => $rows->count(), 'sum' => $rows->sum(fn ($row) => $row->amount->centavos())];
        }

        return $totals;
    }

    private function assertForeignKeysResolve(): void
    {
        // Plain queries, not PRAGMA foreign_key_check, so the same check runs on Postgres.
        foreach (self::LEDGER as $table) {
            $this->assertSame(0, DB::table($table)->whereNotIn('user_id', DB::table('users')->select('id'))->count());
        }
        foreach (['recurring', 'expenses'] as $table) {
            $this->assertSame(0, DB::table($table)->whereNotIn('category_id', DB::table('categories')->select('id'))->count());
        }
    }

    /** @return array<string, mixed> */
    private function schemaOf(string $table): array
    {
        $indexes = collect(Schema::getIndexes($table))
            ->map(fn (array $index) => ['columns' => $index['columns'], 'unique' => $index['unique'], 'primary' => $index['primary']])
            ->sortBy(fn (array $index) => implode(',', $index['columns']))
            ->values()->all();

        return [
            'columns' => collect(Schema::getColumns($table))->map(fn (array $column) => [$column['name'], $column['type_name'], $column['nullable']])->all(),
            'indexes' => $indexes,
            'foreign_keys' => collect(Schema::getForeignKeys($table))->pluck('columns')->sort()->values()->all(),
        ];
    }

    public function test_migration_encrypts_existing_rows_without_changing_them(): void
    {
        $this->rollBackTheMigration();
        $this->insertPlainLedger();
        $before = $this->snapshot();
        $expenseTotals = $this->perUserTotals(Expense::class);
        $orderTotals = $this->perUserTotals(Recurring::class);

        $this->artisan('migrate')->assertSuccessful();

        $raw = fn (string $table, string $key) => DB::table($table)->where('id', $this->ids[$key])->first();
        foreach (['lunch', 'silent', 'small', 'voided'] as $key) {
            $this->assertTrue(LedgerCipher::isEncrypted((string) $raw('expenses', $key)->amount), "{$key} amount is plain");
        }
        $this->assertTrue(LedgerCipher::isEncrypted((string) $raw('expenses', 'lunch')->note));
        $this->assertTrue(LedgerCipher::isEncrypted((string) $raw('expenses', 'voided')->note));
        $this->assertNull($raw('expenses', 'silent')->note);
        $this->assertTrue(LedgerCipher::isEncrypted((string) $raw('recurring', 'gym')->amount));
        $this->assertTrue(LedgerCipher::isEncrypted((string) $raw('recurring', 'gym')->note));
        $this->assertTrue(LedgerCipher::isEncrypted((string) $raw('categories', 'food1')->name));
        // A fresh IV per value: two ₱150.00 entries don't look alike.
        $this->assertNotSame($raw('expenses', 'lunch')->amount, $raw('expenses', 'silent')->amount);

        // Every row reads back exactly its old value; timestamps are untouched.
        foreach ($before as $table => $rows) {
            foreach ($rows as $old) {
                $new = (array) DB::table($table)->where('id', $old['id'])->first();
                foreach ($old as $column => $value) {
                    $stored = $new[$column];
                    $plain = match (true) {
                        $column === 'amount' => LedgerCipher::decryptAmount((string) $stored),
                        in_array($column, ['note', 'name'], true) && $stored !== null => LedgerCipher::decrypt((string) $stored),
                        default => $stored,
                    };
                    $this->assertEquals($value, $plain, "{$table}.{$column} of row {$old['id']} changed");
                }
            }
        }

        $this->actingAs($this->first);
        $lunch = Expense::query()->findOrFail($this->ids['lunch']);
        $this->assertSame(15000, $lunch->amount->centavos());
        $this->assertSame('Lunch', $lunch->note);
        $this->assertSame('Oops', Expense::onlyTrashed()->findOrFail($this->ids['voided'])->note);
        $this->assertSame('Gym', Recurring::query()->findOrFail($this->ids['gym'])->note);
        $this->assertSame('Food', Category::query()->findOrFail($this->ids['food1'])->name);

        $index1 = $raw('categories', 'food1')->name_index;
        $index2 = $raw('categories', 'food2')->name_index;
        $this->assertSame(LedgerCipher::fingerprint($this->first->id, 'Food'), $index1);
        $this->assertSame(LedgerCipher::fingerprint($this->second->id, 'Food'), $index2);
        $this->assertNotSame($index1, $index2);

        $this->assertSame($expenseTotals, $this->perUserTotals(Expense::class));
        $this->assertSame($orderTotals, $this->perUserTotals(Recurring::class));
        $this->assertSame([2, 1, 4], array_map(fn (string $table) => DB::table($table)->count(), self::LEDGER));
        $this->assertForeignKeysResolve();
    }

    public function test_migration_skips_values_already_encrypted(): void
    {
        $this->rollBackTheMigration();
        $this->insertPlainLedger();
        $this->artisan('migrate')->assertSuccessful();

        // Down and up again over a ledger where one value was left encrypted by hand: it
        // is not encrypted twice.
        $this->rollBackTheMigration();
        DB::table('expenses')->where('id', $this->ids['lunch'])->update(['note' => LedgerCipher::encrypt('Lunch')]);
        $this->artisan('migrate')->assertSuccessful();

        $note = (string) DB::table('expenses')->where('id', $this->ids['lunch'])->value('note');
        $this->assertSame('Lunch', LedgerCipher::decrypt($note));
    }

    public function test_migration_stores_an_empty_note_as_null(): void
    {
        $this->rollBackTheMigration();
        $this->insertPlainLedger();
        DB::table('expenses')->where('id', $this->ids['lunch'])->update(['note' => '']);
        DB::table('recurring')->where('id', $this->ids['gym'])->update(['note' => '']);

        $this->artisan('migrate')->assertSuccessful();

        // As the cast saves it: "no note" is never ciphertext of ''.
        $this->assertNull(DB::table('expenses')->where('id', $this->ids['lunch'])->value('note'));
        $this->assertNull(DB::table('recurring')->where('id', $this->ids['gym'])->value('note'));
        $this->assertSame(self::EDITED, DB::table('expenses')->where('id', $this->ids['lunch'])->value('updated_at'));
    }

    public function test_rollback_refuses_an_amount_that_is_not_an_integer(): void
    {
        $this->rollBackTheMigration();
        $this->insertPlainLedger();
        $this->artisan('migrate')->assertSuccessful();

        $garbage = LedgerCipher::encrypt('12.50');
        DB::table('expenses')->where('id', $this->ids['lunch'])->update(['amount' => $garbage]);

        try {
            $this->artisan('migrate:rollback', ['--path' => self::MIGRATION]);
            $this->fail('The rollback wrote a non-integer amount.');
        } catch (DecryptException) {
            // Nothing changed: still encrypted, still text, and the migration still counts as run.
        }

        $this->assertSame($garbage, DB::table('expenses')->where('id', $this->ids['lunch'])->value('amount'));
        $this->assertTrue(LedgerCipher::isEncrypted((string) DB::table('expenses')->where('id', $this->ids['small'])->value('amount')));
        $this->assertTrue(Schema::hasColumn('categories', 'name_index'));

        // Put it right, so the teardown rollback can run.
        DB::table('expenses')->where('id', $this->ids['lunch'])->update(['amount' => LedgerCipher::encrypt('15000')]);
    }

    public function test_migration_rolls_back_exactly(): void
    {
        $this->rollBackTheMigration();
        $this->insertPlainLedger();
        $before = $this->snapshot();
        $schema = array_map(fn (string $table) => $this->schemaOf($table), self::LEDGER);

        $this->artisan('migrate')->assertSuccessful();
        $this->assertNotSame($schema, array_map(fn (string $table) => $this->schemaOf($table), self::LEDGER));
        $this->rollBackTheMigration();

        // Same types, indexes and keys; same values, as the same PHP types, and timestamps.
        $this->assertSame($schema, array_map(fn (string $table) => $this->schemaOf($table), self::LEDGER));
        $this->assertSame($before, $this->snapshot());
        $this->assertForeignKeysResolve();

        // And up again. This also leaves the migration as the last batch, so the teardown
        // rollback undoes only it: rolling back further would bring back the global unique
        // name, which the two accounts' "Food" break.
        $this->artisan('migrate')->assertSuccessful();
        $this->assertSame(15000, LedgerCipher::decryptAmount((string) DB::table('expenses')->where('id', $this->ids['lunch'])->value('amount')));
    }
}
