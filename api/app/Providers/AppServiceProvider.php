<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\Expense;
use App\Models\Invitation;
use App\Models\Recurring;
use Closure;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Password::defaults(fn () => Password::min(8)->uncompromised());

        // Fortify's views are off, so there is no password.reset route; the link opens the SPA instead.
        ResetPassword::createUrlUsing(fn ($user, string $token) => config('app.frontend_url').'/reset-password?token='.$token.'&email='.urlencode($user->email));

        $this->bindRouteIds();
    }

    // Here, not in routes/api.php: route:cache skips the route files, so a Route::bind there
    // vanishes in production — and the voided ones have no implicit binding to fall back on.
    //
    // Bindings 404 with the contract's per-resource message, and do so before any body
    // validation — binding resolves in middleware, ahead of the controller. Auth runs
    // before binding (middleware priority), so the ownership scope already applies and
    // another user's id 404s exactly like one that doesn't exist. The admin middleware runs
    // before it too, so a non-admin never reaches the invitation binding.
    private function bindRouteIds(): void
    {
        $this->bindId('category', fn (int $id) => Category::find($id), 'That category is gone.');
        $this->bindId('expense', fn (int $id) => Expense::find($id), 'That entry is not on the roll.');
        $this->bindId('recurring', fn (int $id) => Recurring::find($id), 'That standing order is gone.');
        // Restore only ever targets a voided row: a live or unknown id has nothing to bring back.
        $this->bindId('voidedExpense', fn (int $id) => Expense::onlyTrashed()->find($id), 'That entry is not on the roll.');
        $this->bindId('voidedRecurring', fn (int $id) => Recurring::onlyTrashed()->find($id), 'That standing order is gone.');
        // Invitation has no owner scope: the list is the admin's, not any one ledger's.
        $this->bindId('invitation', fn (int $id) => Invitation::find($id), 'That invite is gone.');
    }

    // Only a positive id that fits a bigint reaches the query: Postgres errors on 'abc' (or a
    // number past bigint) where SQLite just finds nothing, so anything else is the same 404.
    private function bindId(string $parameter, Closure $find, string $message): void
    {
        Route::bind($parameter, function (string $value) use ($find, $message) {
            $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

            return ($id === false ? null : $find($id)) ?? abort(404, $message);
        });
    }
}
