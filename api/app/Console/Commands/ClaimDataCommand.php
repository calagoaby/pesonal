<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ClaimDataCommand extends Command
{
    protected $signature = 'pesonal:claim {email : The account that takes the unowned rows}';

    protected $description = 'Give every category, standing order and expense without an owner to one account';

    public function handle(): int
    {
        $email = strtolower((string) $this->argument('email'));
        $user = User::where('email', $email)->first();

        if ($user === null) {
            $this->error("No account with the email {$email}.");

            return self::FAILURE;
        }

        // Unowned rows only exist before require_user_id, so before the encryption: this runs
        // on the plaintext schema. Raw queries, then: no casts or hooks that expect ciphertext
        // or name_index, no ownership scope (nobody is signed in), soft-deleted rows included,
        // and updated_at left alone (the app reads it as "paused since" and "last changed").
        // The next migrate encrypts and fingerprints what moved here.
        [$merged, $categories, $orders, $expenses] = DB::transaction(function () use ($user) {
            $merged = $this->mergeClashingCategories($user);

            return [
                $merged,
                ...array_map(
                    fn (string $table) => DB::table($table)->whereNull('user_id')->update(['user_id' => $user->id]),
                    ['categories', 'recurring', 'expenses'],
                ),
            ];
        });

        $this->info("Merged {$merged} categories into ones the account already had.");
        $this->info("Claimed {$categories} categories, {$orders} standing orders, {$expenses} expenses for {$user->email}.");

        return self::SUCCESS;
    }

    /**
     * Names are unique per account, so an unowned category named like one the account
     * already has (the defaults from sign-up, say) can't simply change hands. Its
     * expenses and standing orders move to the account's own category, and the
     * duplicate goes. Returns how many were merged.
     *
     * Compared trimmed, in PHP, as the name fingerprint does: "Food " merging into "Food"
     * here is what keeps the next migration's unique index from failing.
     */
    private function mergeClashingCategories(User $user): int
    {
        $owned = DB::table('categories')->where('user_id', $user->id)->orderBy('id')->get(['id', 'name'])
            ->reverse()->mapWithKeys(fn (object $row) => [trim((string) $row->name) => (int) $row->id]);

        $clashing = DB::table('categories')->whereNull('user_id')->get(['id', 'name'])
            ->filter(fn (object $row) => $owned->has(trim((string) $row->name)));

        foreach ($clashing as $duplicate) {
            $target = $owned[trim((string) $duplicate->name)];

            foreach (['expenses', 'recurring'] as $table) {
                DB::table($table)->where('category_id', $duplicate->id)->update(['category_id' => $target]);
            }
            DB::table('categories')->where('id', $duplicate->id)->delete();
        }

        return $clashing->count();
    }
}
