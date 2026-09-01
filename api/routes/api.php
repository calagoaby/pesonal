<?php

use App\Http\Controllers\CategoryController;
use App\Models\Category;
use Illuminate\Support\Facades\Route;

// Routes here are auto-prefixed with /api and given the api middleware group,
// so never add a manual /api prefix. See docs/backend-plan.md.

// Bind {category} so a missing id 404s with the contract's message, and does so
// before any body validation — binding resolves in middleware, ahead of the controller.
Route::bind('category', fn (string $id) => Category::find($id) ?? abort(404, 'That category is gone.'));

Route::apiResource('categories', CategoryController::class);