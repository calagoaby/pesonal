<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Expense;
use App\Models\Recurring;
use App\Models\Scopes\OwnedByCurrentUser;
use App\Models\User;
use App\Support\LedgerCipher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EncryptLedgerCommandTest extends TestCase
{
    use RefreshDatabase;

    private const STAMP = '2026-01-01 00:00:00';

    private const EDITED = '2026-03-04 05:06:07';

    /** @return array<string, array<int, array<string, mixed>>> */
    private function snapshot(): array
    {
        return collect(['categories', 'recurring', 'expenses'])->mapWithKeys(fn (string $table) => [
            $table => DB::table($table)->orderBy('id')->get()->map(fn (object $row) => (array) $row)->all(),
        ])->all();
    }

    private function raw(string $table, int $id): object
    {
        return DB::table($table)->where('id', $id)->sole();
    }

    public function test_encrypt_ledger_encrypts_stragglers_and_is_idempotent(): void
    {
        $user = User::factory()->create();

        // Rows the app wrote: already encrypted, and left byte for byte.
        $food = Category::factory()->create(['user_id' => $user->id, 'name' => 'Food']);
        $encrypted = Expense::factory()->create(['user_id' => $user->id, 'category_id' => $food->id]);
        $untouched = (array) $this->raw('expenses', $encrypted->id);

        // Rows an older container wrote during a deploy: plain, with a stale fingerprint.
        $fun = DB::table('categories')->insertGetId([
            'user_id' => $user->id, 'name' => 'Fun', 'name_index' => 'stale', 'color' => '#B0577B', 'emoji' => '🎉',
            'created_at' => self::STAMP, 'updated_at' => self::EDITED,
        ]);
        $plainRow = fn (int $amount, ?string $note, ?string $deletedAt = null) => DB::table('expenses')->insertGetId([
            'user_id' => $user->id, 'amount' => (string) $amount, 'date' => '2026-06-01', 'note' => $note, 'category_id' => $fun,
            'recurring_id' => null, 'created_at' => self::STAMP, 'updated_at' => self::EDITED, 'deleted_at' => $deletedAt,
        ]);
        $kape = $plainRow(15000, 'Kape');
        $voided = $plainRow(2500, 'Oops', self::EDITED);
        $blank = $plainRow(999, '');
        $gym = DB::table('recurring')->insertGetId([
            'user_id' => $user->id, 'amount' => '50000', 'note' => 'Gym', 'day_of_month' => 1, 'start_date' => '2026-01-01',
            'end_date' => null, 'active' => 1, 'category_id' => $fun, 'created_at' => self::STAMP, 'updated_at' => self::EDITED,
        ]);
        // An encrypted name whose fingerprint went stale (a rotated APP_KEY, say).
        DB::table('categories')->where('id', $food->id)->update(['name_index' => 'stale-too']);

        $this->artisan('pesonal:encrypt-ledger')
            ->expectsOutput('Encrypted 3 expenses, 1 standing orders, 1 categories.')
            ->expectsOutput('Fixed 2 category name fingerprints.')
            ->doesntExpectOutputToContain('Kape')
            ->assertSuccessful();

        foreach ([$kape, $voided, $blank] as $id) {
            $this->assertTrue(LedgerCipher::isEncrypted((string) $this->raw('expenses', $id)->amount));
            $this->assertSame(self::EDITED, $this->raw('expenses', $id)->updated_at);
        }
        $this->assertSame('Kape', LedgerCipher::decrypt((string) $this->raw('expenses', $kape)->note));
        $this->assertSame('Oops', LedgerCipher::decrypt((string) $this->raw('expenses', $voided)->note));
        $this->assertNull($this->raw('expenses', $blank)->note);
        $this->assertSame(50000, LedgerCipher::decryptAmount((string) $this->raw('recurring', $gym)->amount));
        $this->assertSame('Gym', LedgerCipher::decrypt((string) $this->raw('recurring', $gym)->note));
        $this->assertSame('Fun', LedgerCipher::decrypt((string) $this->raw('categories', $fun)->name));
        $this->assertSame(LedgerCipher::fingerprint($user->id, 'Fun'), $this->raw('categories', $fun)->name_index);
        $this->assertSame(LedgerCipher::fingerprint($user->id, 'Food'), $this->raw('categories', $food->id)->name_index);
        $this->assertSame(self::EDITED, $this->raw('categories', $fun)->updated_at);
        $this->assertSame($untouched, (array) $this->raw('expenses', $encrypted->id));

        // The models read the values back.
        $this->actingAs($user);
        $this->assertSame(15000, Expense::query()->findOrFail($kape)->amount->centavos());
        $this->assertSame(2500, Expense::onlyTrashed()->findOrFail($voided)->amount->centavos());
        $this->assertSame('Gym', Recurring::query()->findOrFail($gym)->note);

        // A second run changes nothing: same ciphertext, same timestamps.
        $before = $this->snapshot();
        $this->artisan('pesonal:encrypt-ledger')
            ->expectsOutput('Encrypted 0 expenses, 0 standing orders, 0 categories.')
            ->expectsOutput('Fixed 0 category name fingerprints.')
            ->assertSuccessful();
        $this->assertSame($before, $this->snapshot());
    }

    public function test_encrypt_ledger_reports_a_duplicate_name_without_failing(): void
    {
        $user = User::factory()->create();
        $food = Category::factory()->create(['user_id' => $user->id, 'name' => 'Food']);

        // Renamed to "Food " by an older container: trimmed, it is the same name as $food.
        $renamed = DB::table('categories')->insertGetId([
            'user_id' => $user->id, 'name' => 'Food ', 'name_index' => LedgerCipher::fingerprint($user->id, 'Snacks'),
            'color' => '#C9692E', 'emoji' => '🍔', 'created_at' => self::STAMP, 'updated_at' => self::EDITED,
        ]);
        $other = Category::factory()->create(['user_id' => $user->id, 'name' => 'Fun']);
        DB::table('categories')->where('id', $other->id)->update(['name_index' => 'stale']);

        $this->artisan('pesonal:encrypt-ledger')
            ->expectsOutput('Encrypted 0 expenses, 0 standing orders, 1 categories.')
            ->expectsOutput('Fixed 1 category name fingerprints.')
            ->expectsOutput("Categories {$renamed} and {$food->id} of user {$user->id} now have the same name; category {$renamed} keeps its old fingerprint until one is renamed.")
            ->doesntExpectOutputToContain('Food')
            ->assertSuccessful();

        // The name is encrypted anyway, the clashing row keeps its fingerprint, and the
        // rest of the run went on.
        $this->assertSame('Food ', LedgerCipher::decrypt((string) $this->raw('categories', $renamed)->name));
        $this->assertSame(LedgerCipher::fingerprint($user->id, 'Snacks'), $this->raw('categories', $renamed)->name_index);
        $this->assertSame(LedgerCipher::fingerprint($user->id, 'Food'), $this->raw('categories', $food->id)->name_index);
        $this->assertSame(LedgerCipher::fingerprint($user->id, 'Fun'), $this->raw('categories', $other->id)->name_index);
        $this->assertSame(3, Category::withoutGlobalScope(OwnedByCurrentUser::class)->count());
    }
}
