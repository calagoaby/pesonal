<?php

namespace Tests\Feature\Auth;

use App\Models\Invitation;
use App\Models\User;
use App\Support\DefaultCategories;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RegisterLoginTest extends TestCase
{
    use RefreshDatabase;

    private string $pwnedBody = '';

    private const THROTTLE_COPY = 'Too many tries. Take a breath, try again in a minute.';

    protected function setUp(): void
    {
        parent::setUp();

        // uncompromised() calls the pwned-passwords API; an empty body means "not breached".
        // A closure so a test can swap the body (the first matching fake wins, so re-faking can't).
        Http::fake(['api.pwnedpasswords.com/*' => fn () => Http::response($this->pwnedBody)]);

        // Sign-up is invite-only; InvitationTest covers the refusal.
        Invitation::create(['email' => 'aby@example.com']);
    }

    /** @return array<string, string> */
    private function registration(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Aby',
            'email' => 'aby@example.com',
            'password' => 'correct horse battery',
            'password_confirmation' => 'correct horse battery',
        ], $overrides);
    }

    public function test_register_creates_a_signed_in_user_and_returns_the_user_shape(): void
    {
        $response = $this->fromFrontend()->postJson('/api/auth/register', $this->registration());

        $response->assertCreated();
        $this->assertSame(['id', 'name', 'email', 'avatar_url', 'email_verified', 'is_admin'], array_keys($response->json('data')));
        $this->assertFalse($response->json('data.email_verified'));

        // Drop the cached guard user: the test app is one process, so the model would still say
        // "recently created" and the resource would answer 201. A real second request is a new process.
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('data.id', $response->json('data.id'));
    }

    public function test_registration_creates_the_eight_default_categories(): void
    {
        $id = $this->fromFrontend()->postJson('/api/auth/register', $this->registration())->assertCreated()->json('data.id');

        $categories = DB::table('categories')->where('user_id', $id)->orderBy('id')->get(['name', 'color', 'emoji']);

        $this->assertSame(
            DefaultCategories::ALL,
            $categories->map(fn (object $row) => (array) $row)->all(),
        );
        $this->assertSame(
            ['Housing', 'Groceries', 'Food', 'Transport', 'Fun', 'Utilities', 'Health', 'Other'],
            $categories->pluck('name')->all(),
        );
    }

    public function test_register_rejects_a_taken_email_regardless_of_case(): void
    {
        User::factory()->create(['email' => 'aby@example.com']);

        $this->fromFrontend()
            ->postJson('/api/auth/register', $this->registration(['email' => 'Aby@Example.COM']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_register_rejects_a_short_password(): void
    {
        $this->fromFrontend()
            ->postJson('/api/auth/register', $this->registration(['password' => 'short', 'password_confirmation' => 'short']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');
    }

    public function test_register_rejects_a_breached_password(): void
    {
        $hash = strtoupper(sha1('password123'));
        $this->pwnedBody = substr($hash, 5).':5';

        $this->fromFrontend()
            ->postJson('/api/auth/register', $this->registration(['password' => 'password123', 'password_confirmation' => 'password123']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');
    }

    public function test_login_returns_the_user_and_starts_a_session(): void
    {
        User::factory()->create(['email' => 'aby@example.com', 'password' => 'correct horse battery']);

        $this->fromFrontend()
            ->postJson('/api/auth/login', ['email' => 'aby@example.com', 'password' => 'correct horse battery'])
            ->assertOk()
            ->assertJsonPath('data.email', 'aby@example.com');

        // Without this the cached guard user answers, and the test proves nothing about the session.
        $this->app['auth']->forgetGuards();

        $this->fromFrontend()->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('data.email', 'aby@example.com');
    }

    public function test_login_with_remember_sets_a_cookie_that_signs_in_on_its_own(): void
    {
        User::factory()->create(['email' => 'aby@example.com', 'password' => 'correct horse battery']);
        $recaller = Auth::guard('web')->getRecallerName();

        $response = $this->fromFrontend()
            ->postJson('/api/auth/login', ['email' => 'aby@example.com', 'password' => 'correct horse battery', 'remember' => true])
            ->assertOk()
            ->assertCookie($recaller);

        // A fresh browser session: no signed-in guard, an empty session, only the remember cookie.
        $this->app['auth']->forgetGuards();
        $this->app['session.store']->flush();

        $this->fromFrontend()
            ->withCredentials()->withCookie($recaller, (string) $response->getCookie($recaller)?->getValue())
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('data.email', 'aby@example.com');
    }

    public function test_login_without_remember_sets_no_remember_cookie(): void
    {
        User::factory()->create(['email' => 'aby@example.com', 'password' => 'correct horse battery']);

        $this->fromFrontend()
            ->postJson('/api/auth/login', ['email' => 'aby@example.com', 'password' => 'correct horse battery'])
            ->assertOk()
            ->assertCookieMissing(Auth::guard('web')->getRecallerName());
    }

    public function test_login_with_a_non_string_email_is_422_not_500(): void
    {
        $this->fromFrontend()
            ->postJson('/api/auth/login', ['email' => ['aby@example.com'], 'password' => 'correct horse battery'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_login_is_case_insensitive_on_email(): void
    {
        User::factory()->create(['email' => 'aby@example.com', 'password' => 'correct horse battery']);

        $this->fromFrontend()
            ->postJson('/api/auth/login', ['email' => 'ABY@Example.com', 'password' => 'correct horse battery'])
            ->assertOk()
            ->assertJsonPath('data.email', 'aby@example.com');
    }

    public function test_login_with_a_wrong_password_is_422(): void
    {
        User::factory()->create(['email' => 'aby@example.com', 'password' => 'correct horse battery']);

        $this->fromFrontend()
            ->postJson('/api/auth/login', ['email' => 'aby@example.com', 'password' => 'wrong wrong wrong'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_logout_ends_the_session(): void
    {
        User::factory()->create(['email' => 'aby@example.com', 'password' => 'correct horse battery']);
        $this->fromFrontend()->postJson('/api/auth/login', ['email' => 'aby@example.com', 'password' => 'correct horse battery'])->assertOk();

        $this->fromFrontend()->postJson('/api/auth/logout')->assertNoContent();

        // The test client keeps one app instance; drop the cached guard user so the
        // next request re-reads the (now flushed) session like a fresh browser request would.
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/user')
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'Unauthenticated.']);
    }

    public function test_login_throttles_after_five_attempts(): void
    {
        User::factory()->create(['email' => 'aby@example.com', 'password' => 'correct horse battery']);
        $attempt = ['email' => 'aby@example.com', 'password' => 'wrong wrong wrong'];

        for ($i = 0; $i < 5; $i++) {
            $this->fromFrontend()->postJson('/api/auth/login', $attempt)->assertUnprocessable();
        }

        $this->fromFrontend()->postJson('/api/auth/login', $attempt)
            ->assertStatus(429)
            ->assertExactJson(['message' => self::THROTTLE_COPY]);
    }

    public function test_a_user_with_no_password_cannot_log_in_with_any_password(): void
    {
        User::factory()->create(['email' => 'aby@example.com', 'password' => null]);

        $this->fromFrontend()
            ->postJson('/api/auth/login', ['email' => 'aby@example.com', 'password' => 'anything at all'])
            ->assertUnprocessable();
    }
}
