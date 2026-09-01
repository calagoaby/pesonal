<?php

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ExpenseController;
use App\Models\Category;
use App\Models\Expense;
use Illuminate\Support\Facades\Route;

// Routes here are auto-prefixed with /api and given the api middleware group,
// so never add a manual /api prefix. See docs/backend-plan.md.

// Bindings 404 with the contract's per-resource message, and do so before any body
// validation — binding resolves in middleware, ahead of the controller.
Route::bind('category', fn (string $id) => Category::find($id) ?? abort(404, 'That category is gone.'));
Route::bind('expense', fn (string $id) => Expense::find($id) ?? abort(404, 'That entry is not on the roll.'));

Route::apiResource('categories', CategoryController::class);
Route::apiResource('expenses', ExpenseController::class);