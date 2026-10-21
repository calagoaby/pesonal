<?php

use App\Http\Controllers\Admin\InvitationController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DeleteAccountController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\RecurringController;
use App\Http\Controllers\StatsController;
use App\Http\Resources\UserResource;
use App\Models\Category;
use App\Models\Expense;
use App\Models\Invitation;
use App\Models\Recurring;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Routes here are auto-prefixed with /api and given the api middleware group,
// so never add a manual /api prefix. See backend-plan.md in the docs folder outside the repo (../docs/pesonal/).

Route::get('user', fn (Request $request) => new UserResource($request->user()))->middleware('auth:sanctum');
// No verified.api: an account you can't use yet should still be removable.
Route::delete('user', DeleteAccountController::class)->middleware('auth:sanctum');

// Bindings 404 with the contract's per-resource message, and do so before any body
// validation — binding resolves in middleware, ahead of the controller. Auth runs
// before binding (middleware priority), so the ownership scope already applies and
// another user's id 404s exactly like one that doesn't exist.
// Only a positive id that fits a bigint reaches the query: Postgres errors on 'abc' (or a
// number past bigint) where SQLite just finds nothing, so anything else is the same 404.
$bindId = function (string $parameter, Closure $find, string $message): void {
    Route::bind($parameter, function (string $value) use ($find, $message) {
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return ($id === false ? null : $find($id)) ?? abort(404, $message);
    });
};

$bindId('category', fn (int $id) => Category::find($id), 'That category is gone.');
$bindId('expense', fn (int $id) => Expense::find($id), 'That entry is not on the roll.');
$bindId('recurring', fn (int $id) => Recurring::find($id), 'That standing order is gone.');
// Restore only ever targets a voided row: a live or unknown id has nothing to bring back.
$bindId('voidedExpense', fn (int $id) => Expense::onlyTrashed()->find($id), 'That entry is not on the roll.');
$bindId('voidedRecurring', fn (int $id) => Recurring::onlyTrashed()->find($id), 'That standing order is gone.');

// The ledger needs a signed-in, verified user. auth:sanctum comes first because
// verified.api lets a missing user through.
Route::middleware(['auth:sanctum', 'verified.api'])->group(function () {
    Route::apiResource('categories', CategoryController::class);
    Route::apiResource('expenses', ExpenseController::class);
    Route::apiResource('recurring', RecurringController::class);
    Route::post('expenses/{voidedExpense}/restore', [ExpenseController::class, 'restore']);
    Route::post('recurring/{voidedRecurring}/restore', [RecurringController::class, 'restore']);

    Route::get('stats/monthly', [StatsController::class, 'monthly']);
    Route::get('stats/trend', [StatsController::class, 'trend']);
});

// The owner's invite list. Not under auth:sanctum, which would answer a stranger 401: admin
// resolves the user itself and answers everyone else a missing-route 404. It runs before the
// throttle and the binding below (middleware priority), so a probe can't tell either way.
// Invitation has no owner scope: the list is the admin's, not any one ledger's.
$bindId('invitation', fn (int $id) => Invitation::find($id), 'That invite is gone.');

Route::middleware(['admin', 'throttle:30,1'])->prefix('admin')->group(function () {
    Route::get('invitations', [InvitationController::class, 'index']);
    Route::post('invitations', [InvitationController::class, 'store']);
    Route::delete('invitations/{invitation}', [InvitationController::class, 'destroy']);
});
