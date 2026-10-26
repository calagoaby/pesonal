<?php

namespace Tests\Feature;

use App\Casts\EncryptedText;
use App\Casts\MoneyCast;
use App\Models\Category;
use App\Models\Expense;
use App\Support\LedgerCipher;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use UnexpectedValueException;

class EncryptedCastsTest extends TestCase
{
    use RefreshDatabase;

    public function test_money_cast_stores_ciphertext_and_reads_money(): void
    {
        $cast = new MoneyCast;

        $stored = $cast->set(new Expense, 'amount', Money::fromCentavos(15000), []);
        $this->assertIsString($stored);
        $this->assertTrue(LedgerCipher::isEncrypted($stored));
        $this->assertNotSame($stored, $cast->set(new Expense, 'amount', 15000, []));

        $this->assertSame(15000, $cast->get(new Expense, 'amount', $stored, [])->centavos());
        $this->assertSame(15000, $cast->get(new Expense, 'amount', 15000, [])->centavos());
        // SQLite and Postgres can hand back a bigint as a digit string.
        $this->assertSame(15000, $cast->get(new Expense, 'amount', '15000', [])->centavos());
        $this->assertThrows(fn () => $cast->get(new Expense, 'amount', null, []), UnexpectedValueException::class, 'The amount is missing.');
    }

    public function test_casts_compare_decrypted_values_not_ciphertext(): void
    {
        $money = new MoneyCast;
        $this->assertTrue($money->compare(new Expense, 'amount', LedgerCipher::encrypt('15000'), LedgerCipher::encrypt('15000')));
        $this->assertTrue($money->compare(new Expense, 'amount', 15000, LedgerCipher::encrypt('15000')));
        $this->assertFalse($money->compare(new Expense, 'amount', LedgerCipher::encrypt('15000'), LedgerCipher::encrypt('15001')));

        $text = new EncryptedText;
        $this->assertTrue($text->compare(new Expense, 'note', LedgerCipher::encrypt('Lunch'), LedgerCipher::encrypt('Lunch')));
        $this->assertTrue($text->compare(new Expense, 'note', null, null));
        $this->assertFalse($text->compare(new Expense, 'note', LedgerCipher::encrypt('Lunch'), LedgerCipher::encrypt('Dinner')));
        $this->assertFalse($text->compare(new Expense, 'note', null, LedgerCipher::encrypt('Lunch')));
    }

    public function test_encrypted_text_cast_stores_ciphertext_and_nulls_empty_notes(): void
    {
        $cast = new EncryptedText;

        $stored = $cast->set(new Expense, 'note', 'Lunch', []);
        $this->assertIsString($stored);
        $this->assertTrue(LedgerCipher::isEncrypted($stored));
        $this->assertSame('Lunch', $cast->get(new Expense, 'note', $stored, []));

        $this->assertNull($cast->set(new Expense, 'note', '', []));
        $this->assertNull($cast->set(new Expense, 'note', null, []));
        $this->assertNull($cast->get(new Expense, 'note', null, []));
        $this->assertSame('Gym', $cast->get(new Category, 'name', 'Gym', []));
    }

    public function test_models_store_ciphertext_and_read_plain(): void
    {
        $expense = Expense::factory()->create(['amount' => 15000, 'note' => 'Lunch']);
        // The ownership scope hides every row when nobody is signed in.
        $this->actingAs($expense->user);
        $category = Category::factory()->create(['name' => 'Therapy']);
        $blank = Expense::factory()->create(['note' => '']);

        $raw_expense = DB::table('expenses')->where('id', $expense->id)->first();
        $raw_category = DB::table('categories')->where('id', $category->id)->first();
        $this->assertTrue(LedgerCipher::isEncrypted((string) $raw_expense?->amount));
        $this->assertTrue(LedgerCipher::isEncrypted((string) $raw_expense?->note));
        $this->assertTrue(LedgerCipher::isEncrypted((string) $raw_category?->name));
        $this->assertNull(DB::table('expenses')->where('id', $blank->id)->value('note'));

        $fresh = Expense::query()->findOrFail($expense->id);
        $this->assertSame(15000, $fresh->amount->centavos());
        $this->assertSame('Lunch', $fresh->note);

        $fresh->amount = 15000;
        $fresh->note = 'Lunch';
        $this->assertFalse($fresh->isDirty(['amount', 'note']));

        $this->assertSame('Therapy', Category::query()->findOrFail($category->id)->name);
    }
}
