<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class SocialSignInTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The redirect refuses to start without credentials; the real .env has none yet.
        config([
            'services.google.client_id' => 'test-google-id',
            'services.facebook.client_id' => 'test-facebook-id',
        ]);
    }

    /** @param  array<string, mixed>  $overrides */
    private function socialUser(array $overrides = []): SocialiteUser
    {
        $attributes = array_merge([
            'id' => 'g-1',
            'name' => 'Aby Cruz',
            'email' => 'Aby@Example.com',
            'avatar' => 'https://example.com/aby.jpg',
            // Google's userinfo field; Facebook only ever sends confirmed emails and has none.
            'email_verified' => true,
        ], $overrides);

        return (new SocialiteUser)->setRaw($attributes)->map($attributes);
    }

    private function provider(string $name, SocialiteUser $social): void
    {
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->andReturn($social);

        Socialite::shouldReceive('driver')->with($name)->andReturn($provider);
    }

    public function test_a_new_google_user_is_created_verified_with_defaults_and_signed_in(): void
    {
        $this->provider('google', $this->socialUser());

        $this->get('/api/auth/google/callback')->assertRedirect('http://localhost:3000/');

        $user = User::sole();
        $this->assertSame('aby@example.com', $user->email);
        $this->assertSame('Aby Cruz', $user->name);
        $this->assertSame('https://example.com/aby.jpg', $user->avatar_url);
        $this->assertNull($user->password);
        $this->assertNotNull($user->email_verified_at);
        $this->assertSame(8, DB::table('categories')->where('user_id', $user->id)->count());
        $this->assertSame(
            [['provider' => 'google', 'provider_user_id' => 'g-1']],
            $user->social_accounts()->get(['provider', 'provider_user_id'])->toArray(),
        );
        $this->assertAuthenticatedAs($user);
    }

    public function test_a_new_facebook_user_is_created_without_an_email_verified_flag(): void
    {
        $this->provider('facebook', $this->socialUser(['id' => 'fb-1', 'email_verified' => null]));

        $this->get('/api/auth/facebook/callback')->assertRedirect('http://localhost:3000/');

        $user = User::sole();
        $this->assertSame('facebook', $user->social_accounts()->sole()->provider);
        $this->assertAuthenticatedAs($user);
    }

    public function test_an_existing_social_account_signs_its_user_in(): void
    {
        $user = User::factory()->create(['email' => 'someone-else@example.com']);
        $user->social_accounts()->create(['provider' => 'google', 'provider_user_id' => 'g-1']);
        // The provider's email no longer matches: the (provider, id) pair decides, not the email.
        $this->provider('google', $this->socialUser());

        $this->get('/api/auth/google/callback')->assertRedirect('http://localhost:3000/');

        $this->assertSame(1, User::count());
        $this->assertSame(1, DB::table('social_accounts')->count());
        $this->assertAuthenticatedAs($user);
    }

    public function test_a_verified_email_user_gets_linked(): void
    {
        $user = User::factory()->create(['email' => 'aby@example.com', 'avatar_url' => null]);
        $user->social_accounts()->create(['provider' => 'facebook', 'provider_user_id' => 'fb-1']);
        $password = $user->password;
        $rememberToken = $user->remember_token;
        $this->provider('google', $this->socialUser());

        $this->get('/api/auth/google/callback')->assertRedirect('http://localhost:3000/');

        $user->refresh();
        $this->assertSame(1, User::count());
        $this->assertSame($password, $user->password);
        $this->assertSame($rememberToken, $user->remember_token);
        $this->assertSame('https://example.com/aby.jpg', $user->avatar_url);
        $this->assertEqualsCanonicalizing(['facebook', 'google'], $user->social_accounts()->pluck('provider')->all());
        $this->assertAuthenticatedAs($user);
    }

    public function test_an_existing_avatar_is_kept(): void
    {
        $user = User::factory()->create(['email' => 'aby@example.com', 'avatar_url' => 'https://example.com/mine.jpg']);
        $this->provider('google', $this->socialUser());

        $this->get('/api/auth/google/callback')->assertRedirect('http://localhost:3000/');

        $this->assertSame('https://example.com/mine.jpg', $user->refresh()->avatar_url);
    }

    public function test_an_unverified_email_user_is_taken_over(): void
    {
        // Stored lower-case (the model lower-cases on write); the provider sends mixed case.
        $user = User::factory()->unverified()->create(['email' => 'aby@example.com']);
        $other = User::factory()->create(['email' => 'other@example.com']);
        $rememberToken = $user->remember_token;
        foreach ([$user, $other] as $owner) {
            DB::table('sessions')->insert([
                'id' => 'session-of-'.$owner->id,
                'user_id' => $owner->id,
                'payload' => '',
                'last_activity' => time(),
            ]);
        }
        $this->provider('google', $this->socialUser());

        $this->get('/api/auth/google/callback')->assertRedirect('http://localhost:3000/');

        $user->refresh();
        $this->assertSame(2, User::count());
        $this->assertNull($user->password);
        $this->assertNotNull($user->email_verified_at);
        $this->assertNotSame($rememberToken, $user->remember_token);
        $this->assertSame(['session-of-'.$other->id], DB::table('sessions')->pluck('id')->all());
        $this->assertSame('google', $user->social_accounts()->sole()->provider);
        $this->assertAuthenticatedAs($user);
    }

    public function test_a_provider_without_an_email_is_refused(): void
    {
        $this->provider('facebook', $this->socialUser(['id' => 'fb-1', 'email' => null, 'email_verified' => null]));

        $this->get('/api/auth/facebook/callback')->assertRedirect('http://localhost:3000/login?error=no_email');

        $this->assertSame(0, User::count());
        $this->assertSame(0, DB::table('social_accounts')->count());
        $this->assertGuest();
    }

    public function test_a_google_email_google_has_not_verified_is_refused(): void
    {
        // Linking hands over an existing account by email, so the email has to be proven.
        $user = User::factory()->unverified()->create(['email' => 'aby@example.com']);
        $password = $user->password;
        $this->provider('google', $this->socialUser(['email_verified' => false]));

        $this->get('/api/auth/google/callback')->assertRedirect('http://localhost:3000/login?error=failed');

        $user->refresh();
        $this->assertSame(1, User::count());
        $this->assertSame($password, $user->password);
        $this->assertNull($user->email_verified_at);
        $this->assertSame(0, DB::table('social_accounts')->count());
        $this->assertGuest();
    }

    public function test_a_cancelled_sign_in_redirects_with_cancelled(): void
    {
        Socialite::shouldReceive('driver')->never();

        $this->get('/api/auth/google/callback?error=access_denied')
            ->assertRedirect('http://localhost:3000/login?error=cancelled');

        $this->assertGuest();
    }

    public function test_a_provider_failure_redirects_with_failed(): void
    {
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->andThrow(new InvalidStateException);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $this->get('/api/auth/google/callback')->assertRedirect('http://localhost:3000/login?error=failed');

        $this->assertGuest();
    }

    public function test_an_unknown_provider_404s(): void
    {
        $this->get('/api/auth/twitter/redirect')->assertNotFound();
        $this->get('/api/auth/twitter/callback')->assertNotFound();
    }

    public function test_redirect_sends_the_browser_to_the_provider(): void
    {
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('redirect')->andReturn(redirect('https://accounts.google.com/x'));
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $this->get('/api/auth/google/redirect')->assertRedirect('https://accounts.google.com/x');
    }

    public function test_redirect_without_credentials_goes_back_to_login_with_failed(): void
    {
        config(['services.google.client_id' => null]);
        Socialite::shouldReceive('driver')->never();

        $this->get('/api/auth/google/redirect')->assertRedirect('http://localhost:3000/login?error=failed');
    }
}
