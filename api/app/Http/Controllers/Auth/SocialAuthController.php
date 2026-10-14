<?php

namespace App\Http\Controllers\Auth;

use App\Actions\SignInWithProvider;
use App\Exceptions\ProviderEmailMissing;
use App\Exceptions\ProviderEmailUnverified;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;
use Throwable;

// Both actions are full-page browser trips, not fetches, so every outcome is a redirect
// to the frontend: home on success, /login?error=<code> otherwise.
class SocialAuthController extends Controller
{
    public function redirect(string $provider): SymfonyRedirect
    {
        // Without credentials Socialite would still build a URL, and the provider would show
        // its own "missing client_id" error page. Our login screen says it better.
        if (empty(config("services.{$provider}.client_id"))) {
            return $this->toLogin('failed');
        }

        try {
            return Socialite::driver($provider)->redirect();
        } catch (Throwable $e) {
            report($e);

            return $this->toLogin('failed');
        }
    }

    public function callback(Request $request, string $provider, SignInWithProvider $signIn): RedirectResponse
    {
        // The provider's access_denied: the user backed out on its screen.
        if ($request->has('error')) {
            return $this->toLogin('cancelled');
        }

        try {
            $user = $signIn->execute($provider, Socialite::driver($provider)->user());
        } catch (ProviderEmailMissing) {
            return $this->toLogin('no_email');
        } catch (InvalidStateException|ProviderEmailUnverified) {
            // A stale or replayed callback (back button, a second tab), or an email the
            // provider hasn't proven: nothing to fix on our side, so nothing to report.
            return $this->toLogin('failed');
        } catch (Throwable $e) {
            report($e);

            return $this->toLogin('failed');
        }

        Auth::login($user, remember: true);
        // A fresh id for the signed-in session, so one planted before the trip is useless.
        $request->session()->regenerate();

        return redirect()->away(config('app.frontend_url').'/');
    }

    private function toLogin(string $error): RedirectResponse
    {
        return redirect()->away(config('app.frontend_url').'/login?error='.$error);
    }
}
