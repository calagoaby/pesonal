<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Http\Responses\FailedPasswordResetResponse;
use App\Http\Responses\LoginResponse;
use App\Http\Responses\PasswordResetLinkResponse;
use App\Http\Responses\RegisterResponse;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\FailedPasswordResetResponse as FailedPasswordResetResponseContract;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(LoginResponseContract::class, LoginResponse::class);
        $this->app->singleton(RegisterResponseContract::class, RegisterResponse::class);
        // bind, not singleton: Fortify passes a status argument when it builds the failed response.
        $this->app->bind(SuccessfulPasswordResetLinkRequestResponse::class, PasswordResetLinkResponse::class);
        $this->app->bind(FailedPasswordResetLinkRequestResponse::class, PasswordResetLinkResponse::class);
        $this->app->bind(FailedPasswordResetResponseContract::class, FailedPasswordResetResponse::class);
    }

    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        RateLimiter::for('login', function (Request $request) {
            // The limiter runs before validation, so `email[]=x` reaches here as an array; key it
            // as empty and let the login request's own rules answer 422.
            $email = $request->input(Fortify::username());
            $throttleKey = Str::transliterate(Str::lower(is_string($email) ? $email : '').'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('auth-mail', fn (Request $request) => Limit::perMinute(6)->by((string) $request->ip()));

        // Fortify registers these routes itself, and the router only loads (or un-caches) its routes
        // during the booted callbacks, so wait a round past them.
        $this->app->booted(fn () => $this->app->booted(function () {
            foreach (Route::getRoutes()->getRoutes() as $route) {
                if (in_array($route->getName(), ['register.store', 'password.email'], true)) {
                    $route->middleware('throttle:auth-mail');
                }
            }
        }));
    }
}
