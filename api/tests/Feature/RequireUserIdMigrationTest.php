<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

// DatabaseMigrations, not RefreshDatabase: rolling a migration back and re-running it
// inside RefreshDatabase's wrapping transaction is unreliable.
class RequireUserIdMigrationTest extends TestCase
{
    use DatabaseMigrations;

    private const MIGRATION = 'database/migrations/2026_10_13_000004_require_user_id_on_ledger_tables.php';

    private const STAMP = '2026-01-01 00:00:00';

    // A fresh test database runs every migration in one batch, so a bare --step 1 would
    // undo whichever sorts last. --path narrows the last batch to this one file.
    private function rollBackTheMigration(): void
    {
        $this->artisan('migrate:rollback', ['--path' => self::MIGRATION])->assertSuccessful();
    }

    private function userIdIsRequired(string $table): bool
    {
        $column = collect(Schema::getColumns($table))->firstWhere('name', 'user_id');

        return $column['nullable'] === false;
    }

    public function test_the_required_migration_refuses_while_rows_are_unowned(): void
    {
        $this->rollBackTheMigration();
        $this->assertFalse($this->userIdIsRequired('categories'));

        DB::table('categories')->insert([
            'name' => 'Food', 'color' => '#C9692E', 'emoji' => '🍔', 'created_at' => self::STAMP, 'updated_at' => self::STAMP,
        ]);

        $this->assertThrows(
            fn () => $this->artisan('migrate')->run(),
            RuntimeException::class,
            '1 categories, 0 expenses, 0 standing orders have no owner. Run php artisan pesonal:claim <email> first.',
        );
        $this->assertFalse($this->userIdIsRequired('categories'));

        User::factory()->create(['email' => 'owner@example.com']);
        $this->artisan('pesonal:claim', ['email' => 'owner@example.com'])->assertSuccessful();

        $this->artisan('migrate')->assertSuccessful();

        foreach (['categories', 'expenses', 'recurring'] as $table) {
            $this->assertTrue($this->userIdIsRequired($table), "{$table}.user_id should be NOT NULL");
        }
        $this->assertSame(1, DB::table('categories')->count());

        // The SQLite rebuild copies each table, so check its foreign key came along and
        // every row still resolves. Plain schema and query calls, not PRAGMA
        // foreign_key_check, so the same check runs on Postgres.
        foreach (['categories', 'expenses', 'recurring'] as $table) {
            $this->assertContains(
                ['user_id'],
                array_column(Schema::getForeignKeys($table), 'columns'),
                "{$table}.user_id lost its foreign key",
            );
            $this->assertSame(0, DB::table($table)->whereNotIn('user_id', DB::table('users')->select('id'))->count());
        }
    }

    public function test_a_category_without_an_owner_is_refused(): void
    {
        $this->expectException(QueryException::class);

        DB::table('categories')->insert([
            'name' => 'Food', 'color' => '#C9692E', 'emoji' => '🍔', 'created_at' => self::STAMP, 'updated_at' => self::STAMP,
        ]);
    }
}
