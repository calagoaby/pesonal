<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class VerificationAndResetTest extends TestCase
{
    use RefreshDatabase;

    private const UNVERIFIED_COPY = 'Check your email — the receipt is not printed until you confirm it.';

    private const FORGOT_COPY = "If it's on file, a reset slip is on its way.";

    protected function setUp(): void
    {
        parent::setUp();

        // uncompromised() calls the pwned-passwords API; an empty body means "not breached".
        Http::fake(['api.pwnedpasswords.com/*' => Http::response('')]);
    }

    public function test_register_sends_a_verification_email(): void
    {
        Notification::fake();

        $this->fromFrontend()->postJson('/api/auth/register', [
            'name' => 'Aby',
            'email' => 'aby@example.com',
            'password' => 'correct horse battery',
            'password_confirmation' => 'correct horse battery',
        ])->assertCreated();

        Notification::assertSentTo(User::where('email', 'aby@example.com')->firstOrFail(), VerifyEmail::class);
    }

    public function test_the_signed_link_verifies_the_user_and_redirects_to_the_frontend(): void
    {
        $user = User::factory()->unverified()->create();
        $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]);

        $response = $this->fromFrontend()->actingAs($user)->get($url);

        $response->assertRedirectContains('http://localhost:3000');
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_a_signed_out_browser_opening_the_verify_link_is_sent_to_sign_in(): void
    {
        $user = User::factory()->unverified()->create();

        $this->get($this->verifyUrl($user))
            ->assertRedirect('http://localhost:3000/login?error=verify_signed_out');

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_a_signed_out_json_call_to_the_verify_link_still_answers_401_json(): void
    {
        $user = User::factory()->unverified()->create();

        $this->getJson($this->verifyUrl($user))
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'Unauthenticated.']);
    }

    public function test_a_signed_out_browser_on_another_api_route_still_gets_json_401(): void
    {
        $this->get('/api/user')
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'Unauthenticated.']);
    }

    private function verifyUrl(User $user): string
    {
        return URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]);
    }

    public function test_resend_answers_202(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();

        $this->fromFrontend()->actingAs($user)
            ->postJson('/api/auth/email/verification-notification')
            ->assertStatus(202);

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_verified_api_middleware_blocks_unverified_users_with_the_contract_message(): void
    {
        Route::middleware(['api', 'auth:sanctum', 'verified.api'])->get('/api/_probe', fn () => 'ok');

        $this->actingAs(User::factory()->unverified()->create())
            ->getJson('/api/_probe')
            ->assertForbidden()
            ->assertExactJson(['message' => self::UNVERIFIED_COPY]);

        $this->actingAs(User::factory()->create())
            ->getJson('/api/_probe')
            ->assertOk();
    }

    public function test_forgot_password_answers_the_same_for_known_and_unknown_emails(): void
    {
        Notification::fake();
        User::factory()->create(['email' => 'aby@example.com']);

        $this->fromFrontend()->postJson('/api/auth/forgot-password', ['email' => 'aby@example.com'])
            ->assertOk()
            ->assertExactJson(['message' => self::FORGOT_COPY]);

        $this->fromFrontend()->postJson('/api/auth/forgot-password', ['email' => 'nobody@example.com'])
            ->assertOk()
            ->assertExactJson(['message' => self::FORGOT_COPY]);
    }

    public function test_the_reset_link_points_at_the_frontend(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'aby@example.com']);

        $this->fromFrontend()->postJson('/api/auth/forgot-password', ['email' => 'aby@example.com'])->assertOk();

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
            $url = $notification->toMail($user)->actionUrl;

            return str_starts_with($url, 'http://localhost:3000/reset-password?token=')
                && str_contains($url, '&email='.urlencode($user->email));
        });
    }

    public function test_reset_password_failure_is_the_same_for_known_and_unknown_emails(): void
    {
        User::factory()->create(['email' => 'aby@example.com']);
        $expected = [
            'message' => 'The given data was invalid.',
            'errors' => ['email' => ['That reset slip is invalid or has expired.']],
        ];

        foreach (['aby@example.com', 'nobody@example.com'] as $email) {
            $this->fromFrontend()->postJson('/api/auth/reset-password', [
                'token' => 'junk-token',
                'email' => $email,
                'password' => 'brand new password',
                'password_confirmation' => 'brand new password',
            ])->assertUnprocessable()->assertExactJson($expected);
        }
    }

    public function test_reset_password_then_login_with_the_new_one_works(): void
    {
        $user = User::factory()->create(['email' => 'aby@example.com', 'password' => 'old password here']);
        $token = Password::createToken($user);

        $this->fromFrontend()->postJson('/api/auth/reset-password', [
            'token' => $token,
            'email' => 'aby@example.com',
            'password' => 'brand new password',
            'password_confirmation' => 'brand new password',
        ])->assertOk();

        $this->fromFrontend()
            ->postJson('/api/auth/login', ['email' => 'aby@example.com', 'password' => 'brand new password'])
            ->assertOk();
    }
}
