<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
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
    }
}
