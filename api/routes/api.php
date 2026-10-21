<?php

use App\Http\Controllers\Admin\InvitationController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DeleteAccountController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\RecurringController;
use App\Http\Controllers\StatsController;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Routes here are auto-prefixed with /api and given the api middleware group,
// so never add a manual /api prefix. See backend-plan.md in the docs folder outside the repo (../docs/pesonal/).

Route::get('user', fn (Request $request) => new UserResource($request->user()))->middleware('auth:sanctum');
// No verified.api: an account you can't use yet should still be removable.
Route::delete('user', DeleteAccountController::class)->middleware('auth:sanctum');

// Route-model bindings (messages, numeric-id guard, voided rows) live in AppServiceProvider:
// route:cache skips this file, so a Route::bind here would vanish in production.

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
// throttle and the route binding (middleware priority), so a probe can't tell either way.
Route::middleware(['admin', 'throttle:30,1'])->prefix('admin')->group(function () {
    Route::get('invitations', [InvitationController::class, 'index']);
    Route::post('invitations', [InvitationController::class, 'store']);
    Route::delete('invitations/{invitation}', [InvitationController::class, 'destroy']);
});
