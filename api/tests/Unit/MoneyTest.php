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
}
