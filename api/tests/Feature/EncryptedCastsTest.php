<?php

namespace Tests\Feature;

use App\Casts\EncryptedItems;
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

    public function test_items_cast_stores_one_ciphertext_and_reads_the_list(): void
    {
        $cast = new EncryptedItems;
        $list = [['name' => 'Adobo', 'amount' => 4500], ['name' => 'Rice', 'amount' => 1000]];

        $stored = $cast->set(new Expense, 'items', $list, []);
        $this->assertIsString($stored);
        $this->assertTrue(LedgerCipher::isEncrypted($stored));
        $this->assertNotSame($stored, $cast->set(new Expense, 'items', $list, []));

        $this->assertSame($list, $cast->get(new Expense, 'items', $stored, []));
    }

    public function test_items_cast_stores_an_empty_list_as_null(): void
    {
        $cast = new EncryptedItems;

        $this->assertNull($cast->set(new Expense, 'items', [], []));
        $this->assertNull($cast->set(new Expense, 'items', null, []));
        $this->assertSame([], $cast->get(new Expense, 'items', null, []));
    }

    public function test_items_cast_throws_on_a_value_that_is_not_a_list_of_items(): void
    {
        $cast = new EncryptedItems;

        foreach (['not json', '{"name":"A","amount":1}', '[{"name":"A"}]', '[{"name":"A","amount":"1"}]', '[{"name":1,"amount":1}]', '[1]'] as $plain) {
            $this->assertThrows(
                fn () => $cast->get(new Expense, 'items', LedgerCipher::encrypt($plain), []),
                UnexpectedValueException::class,
            );
        }
    }

    public function test_items_cast_refuses_to_store_what_it_could_not_read_back(): void
    {
        $cast = new EncryptedItems;
        $model = new Expense;

        $this->assertNull($cast->set($model, 'items', null, []));

        foreach (['rice', [['name' => 'Rice']], [['name' => 'Rice', 'amount' => '100']], ['rice'], ['a' => ['name' => 'Rice', 'amount' => 100]]] as $bad) {
            try {
                $cast->set($model, 'items', $bad, []);
                $this->fail('set() accepted '.json_encode($bad));
            } catch (UnexpectedValueException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_items_cast_compares_decoded_lists(): void
    {
        $cast = new EncryptedItems;
        $a = LedgerCipher::encrypt('[{"name":"Adobo","amount":4500}]');
        $b = LedgerCipher::encrypt('[{"name":"Adobo","amount":4500}]');
        $c = LedgerCipher::encrypt('[{"name":"Adobo","amount":4501}]');

        $this->assertTrue($cast->compare(new Expense, 'items', $a, $b));
        $this->assertTrue($cast->compare(new Expense, 'items', null, null));
        $this->assertFalse($cast->compare(new Expense, 'items', $a, $c));
        $this->assertFalse($cast->compare(new Expense, 'items', null, $a));
    }

    public function test_reading_items_does_not_dirty_the_model(): void
    {
        $expense = Expense::factory()->create(['items' => [['name' => 'Adobo', 'amount' => 4500]]]);

        $fresh = Expense::withoutGlobalScopes()->findOrFail($expense->id);
        $this->assertSame([['name' => 'Adobo', 'amount' => 4500]], $fresh->items);
        $this->assertFalse($fresh->isDirty('items'));

        $fresh->items = [['name' => 'Adobo', 'amount' => 4500]];
        $this->assertFalse($fresh->isDirty('items'));
    }

    public function test_items_are_ciphertext_in_the_table(): void
    {
        Expense::factory()->create(['items' => [['name' => 'Adobo', 'amount' => 4500]]]);
        Expense::factory()->create();

        $stored = DB::table('expenses')->orderBy('id')->pluck('items')->all();
        $this->assertIsString($stored[0]);
        $this->assertTrue(LedgerCipher::isEncrypted($stored[0]));
        $this->assertStringNotContainsString('Adobo', $stored[0]);
        $this->assertNull($stored[1]);
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

    public function test_text_cast_stores_null_for_empty_values_and_stringifies_scalars(): void
    {
        $cast = new EncryptedText;
        $set = fn (mixed $value) => $cast->set(new Expense, 'note', $value, []);

        foreach ([null, '', false, [], new \stdClass] as $empty) {
            $this->assertNull($set($empty));
        }

        $this->assertSame('5', LedgerCipher::decrypt((string) $set(5)));
        $this->assertSame('1', LedgerCipher::decrypt((string) $set(true)));
        $this->assertSame('0', LedgerCipher::decrypt((string) $set(0)));
    }
}
