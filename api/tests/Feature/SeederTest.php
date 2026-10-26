<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\LedgerCipher;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SeederTest extends TestCase
{
    // DatabaseMigrations, not RefreshDatabase: the seeders rely on fixed ids 1..8 for the
    // categories, and a rolled-back Postgres transaction doesn't reset the id sequences.
    use DatabaseMigrations;

    // Pinned on the seeders as they were before they wrote ciphertext (2026-10-26): the
    // demo ledger must read the same once its values are encrypted.
    private const MONTHS = [
        '2026-05' => ['total' => 4643000, 'previous_total' => 0, 'food' => [490000, 16], 'transport' => [168000, 11], 'days' => 31],
        '2026-06' => ['total' => 4604000, 'previous_total' => 4643000, 'food' => [475000, 15], 'transport' => [144000, 10], 'days' => 30],
        '2026-07' => ['total' => 4643000, 'previous_total' => 4604000, 'food' => [490000, 16], 'transport' => [168000, 11], 'days' => 31],
    ];

    public function test_seeded_demo_totals_are_unchanged(): void
    {
        $this->seed();
        $this->actingAs(User::where('email', 'demo@pesonal.test')->sole());

        foreach (self::MONTHS as $month => $pinned) {
            $stats = $this->getJson("/api/stats/monthly?month={$month}")->assertOk()->json('data');

            $this->assertSame($pinned['total'], $stats['total'], $month);
            $this->assertSame($pinned['previous_total'], $stats['previous_total'], $month);
            $this->assertSame([
                [1, 'Housing', 1500000, 1],
                [2, 'Groceries', 1075000, 5],
                [6, 'Utilities', 910000, 5],
                [3, 'Food', ...$pinned['food']],
                [7, 'Health', 260000, 5],
                ...($month === '2026-06'
                    ? [[8, 'Other', 150000, 5], [4, 'Transport', ...$pinned['transport']]]
                    : [[4, 'Transport', ...$pinned['transport']], [8, 'Other', 150000, 5]]),
                [5, 'Fun', 90000, 5],
            ], array_map(fn (array $row) => [$row['category']['id'], $row['category']['name'], $row['total'], $row['count']], $stats['by_category']), $month);
            $this->assertCount($pinned['days'], $stats['daily']);
            $this->assertSame(1539000, $stats['daily'][0]['total'], $month);
            $this->assertSame($pinned['total'], array_sum(array_column($stats['daily'], 'total')), $month);
        }

        $this->getJson('/api/expenses?month=2026-07')->assertOk()
            ->assertJsonPath('data.0.note', 'Kape sa umaga')
            ->assertJsonPath('data.0.amount', 15000)
            ->assertJsonPath('data.0.created_at', '2026-07-31T02:00:00Z')
            ->assertJsonPath('data.1.note', 'Grab to office')
            ->assertJsonPath('data.1.category.name', 'Transport');

        $orders = $this->getJson('/api/recurring')->assertOk()->json('data');
        $this->assertSame([
            [1, 1500000, 'Apartment rent', true, '2026-01-01T00:00:00Z'],
            [2, 180000, 'Internet', true, '2026-03-01T00:00:00Z'],
            [3, 19400, 'Spotify', true, '2026-02-01T00:00:00Z'],
            [4, 150000, 'Gym', false, '2026-05-01T00:00:00Z'],
            [5, 62000, 'Water bill', true, '2026-01-01T00:00:00Z'],
            [6, 108600, 'Netflix', true, '2026-01-01T00:00:00Z'],
        ], array_map(fn (array $order) => [$order['id'], $order['amount'], $order['note'], $order['active'], $order['updated_at']], $orders));
    }

    public function test_seeded_ledger_is_stored_encrypted(): void
    {
        $this->seed();
        $demo = User::where('email', 'demo@pesonal.test')->sole();

        foreach (['expenses', 'recurring'] as $table) {
            foreach (DB::table($table)->get(['amount', 'note']) as $row) {
                $this->assertTrue(LedgerCipher::isEncrypted((string) $row->amount), "{$table}.amount is plain");
                $this->assertTrue(LedgerCipher::isEncrypted((string) $row->note), "{$table}.note is plain");
            }
        }
        foreach (DB::table('categories')->get(['user_id', 'name', 'name_index']) as $row) {
            $this->assertSame($demo->id, $row->user_id);
            $this->assertSame(LedgerCipher::fingerprint($demo->id, LedgerCipher::decrypt($row->name)), $row->name_index);
        }

        // Nothing left for the boot-time command to do.
        $this->artisan('pesonal:encrypt-ledger')
            ->expectsOutput('Encrypted 0 expenses, 0 standing orders, 0 categories.')
            ->expectsOutput('Fixed 0 category name fingerprints.');
    }
}
