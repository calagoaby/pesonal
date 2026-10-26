<?php

namespace Tests\Unit;

use App\Casts\MoneyCast;
use App\Models\Expense;
use App\Support\Money;
use Tests\TestCase;

class MoneyTest extends TestCase
{
    public function test_from_centavos_holds_the_exact_int(): void
    {
        $this->assertSame(15000, Money::fromCentavos(15000)->centavos());
    }

    public function test_cast_get_wraps_a_stored_int_into_money(): void
    {
        $money = (new MoneyCast)->get(new Expense, 'amount', 15000, []);

        $this->assertInstanceOf(Money::class, $money);
        $this->assertSame(15000, $money->centavos());
    }

    public function test_plain_cast_set_stores_a_bare_int_from_a_money_or_a_raw_int(): void
    {
        $cast = new MoneyCast('plain');

        $this->assertSame(15000, $cast->set(new Expense, 'amount', Money::fromCentavos(15000), []));
        $this->assertSame(15000, $cast->set(new Expense, 'amount', 15000, []));
    }
}
