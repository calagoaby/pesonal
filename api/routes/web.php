<?php

use App\Http\Controllers\Auth\SocialAuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Here, not in api.php: these are top-level browser navigations with no Origin header, so
// Sanctum's stateful check would give them no session. Socialite's state check and the
// login both need one, and it's the same session auth:sanctum reads afterwards.
Route::prefix('api/auth')->group(function () {
    Route::get('{provider}/redirect', [SocialAuthController::class, 'redirect'])
        ->whereIn('provider', ['google', 'facebook']);
    Route::get('{provider}/callback', [SocialAuthController::class, 'callback'])
        ->whereIn('provider', ['google', 'facebook']);
});
