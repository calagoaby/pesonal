<?php

namespace Database\Seeders;

use App\Models\Expense;
use App\Support\LedgerCipher;
use Illuminate\Database\Seeder;

class ExpenseSeeder extends Seeder
{
    public function run(int $userId): void
    {
        // Ported straight from mockDb.ts buildExpenses() so the app looks identical
        // after the base URL flips. Deterministic on purpose — no random values.
        $templates = [
            ['note' => 'Lunch with team', 'amount' => 68000, 'category_id' => 3],
            ['note' => 'Grab to office', 'amount' => 24000, 'category_id' => 4],
            ['note' => 'Kape sa umaga', 'amount' => 15000, 'category_id' => 3],
            ['note' => 'SM Groceries', 'amount' => 215000, 'category_id' => 2],
            ['note' => 'Load', 'amount' => 30000, 'category_id' => 8],
            ['note' => 'Milk tea run', 'amount' => 18000, 'category_id' => 5],
            ['note' => 'Jeep fare', 'amount' => 4800, 'category_id' => 4],
            ['note' => 'Meralco', 'amount' => 182000, 'category_id' => 6],
            ['note' => 'Vitamins', 'amount' => 52000, 'category_id' => 7],
            ['note' => 'Merienda', 'amount' => 12000, 'category_id' => 3],
        ];

        $months = [
            ['month' => '2026-07', 'days' => 31],
            ['month' => '2026-06', 'days' => 30],
            ['month' => '2026-05', 'days' => 31],
        ];

        $count = count($templates);
        $rows = [];

        // Newest first (day descending), matching the contract's date desc ordering.
        foreach ($months as $m) {
            for ($day = $m['days']; $day >= 1; $day--) {
                $per_day = $day % 3 === 0 ? 1 : 2;

                for ($slot = 0; $slot < $per_day; $slot++) {
                    $template = $templates[($day + $slot) % $count];
                    $date = sprintf('%s-%02d', $m['month'], $day);
                    $timestamp = sprintf('%s %02d:00:00', $date, $slot + 1);

                    $rows[] = [
                        'user_id' => $userId,
                        'amount' => $template['amount'],
                        'date' => $date,
                        'note' => $template['note'],
                        'category_id' => $template['category_id'],
                        'recurring_id' => null,
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ];
                }

                // Rent lands on the 1st, stamped with recurring template 1.
                if ($day === 1) {
                    $timestamp = sprintf('%s-01 00:00:00', $m['month']);

                    $rows[] = [
                        'user_id' => $userId,
                        'amount' => 1500000,
                        'date' => $m['month'].'-01',
                        'note' => 'Rent share',
                        'category_id' => 1,
                        'recurring_id' => 1,
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ];
                }
            }
        }

        // insert() skips the casts, so the values are encrypted by hand (each with its own IV).
        Expense::insert(array_map(fn (array $row) => [
            ...$row,
            'amount' => LedgerCipher::encrypt((string) $row['amount']),
            'note' => LedgerCipher::encrypt($row['note']),
        ], $rows));
    }
}
