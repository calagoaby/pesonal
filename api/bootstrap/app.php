<?php

use App\Http\Middleware\EnsureEmailIsVerified;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        $middleware->alias(['verified.api' => EnsureEmailIsVerified::class]);
        // Browser navigations go to the SPA login; JSON requests still get a 401.
        $middleware->redirectGuestsTo(fn () => config('app.frontend_url').'/login');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        // The verify link is opened from an email, so a signed-out browser lands on sign-in instead of
        // the bare JSON 401 every api/* route renders. JSON callers still get the 401.
        $exceptions->render(fn (AuthenticationException $e, Request $request) => $request->is('api/auth/email/verify/*') && ! $request->expectsJson()
            ? redirect()->away(config('app.frontend_url').'/login?error=verify_signed_out')
            : null);

        $exceptions->render(fn (ThrottleRequestsException $e, Request $request) => $request->is('api/*')
            ? response()->json(['message' => 'Too many tries. Take a breath, try again in a minute.'], 429, $e->getHeaders())
            : null);

        // Pin the contract's literal 422 message, as ApiFormRequest does for the data routes. The
        // status comes from the exception: Fortify's LockoutResponse throws one with 429.
        $exceptions->render(fn (ValidationException $e, Request $request) => $request->is('api/*')
            ? response()->json(['message' => 'The given data was invalid.', 'errors' => $e->errors()], $e->status)
            : null);
    })->create();
