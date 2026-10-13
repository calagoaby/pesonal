<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Expense;
use App\Models\Recurring;
use App\Models\Scopes\OwnedByCurrentUser;
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

        // Nobody is signed in on the console, so every query opts out of the ownership
        // scope (which would otherwise match nothing) and filters by user_id by hand.
        // Updates go through toBase(): Eloquent's update() would bump updated_at, which
        // the app reads as "paused since" and "last changed".
        [$merged, $categories, $orders, $expenses] = DB::transaction(function () use ($user) {
            $merged = $this->mergeClashingCategories($user);

            return [
                $merged,
                Category::withoutGlobalScope(OwnedByCurrentUser::class)->whereNull('user_id')->toBase()->update(['user_id' => $user->id]),
                Recurring::withoutGlobalScope(OwnedByCurrentUser::class)->withTrashed()->whereNull('user_id')->toBase()->update(['user_id' => $user->id]),
                Expense::withoutGlobalScope(OwnedByCurrentUser::class)->withTrashed()->whereNull('user_id')->toBase()->update(['user_id' => $user->id]),
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
     */
    private function mergeClashingCategories(User $user): int
    {
        $owned = Category::withoutGlobalScope(OwnedByCurrentUser::class)
            ->where('user_id', $user->id)
            ->pluck('id', 'name');

        $clashing = Category::withoutGlobalScope(OwnedByCurrentUser::class)
            ->whereNull('user_id')
            ->whereIn('name', $owned->keys())
            ->get();

        foreach ($clashing as $duplicate) {
            $target = $owned[$duplicate->name];

            Expense::withoutGlobalScope(OwnedByCurrentUser::class)->withTrashed()
                ->where('category_id', $duplicate->id)
                ->toBase()->update(['category_id' => $target]);
            Recurring::withoutGlobalScope(OwnedByCurrentUser::class)->withTrashed()
                ->where('category_id', $duplicate->id)
                ->toBase()->update(['category_id' => $target]);

            $duplicate->delete();
        }

        return $clashing->count();
    }
}
