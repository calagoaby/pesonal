<?php

namespace Tests\Feature;

use App\Models\Invitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class InvitationTest extends TestCase
{
    use RefreshDatabase;

    private const NOT_INVITED_COPY = 'This ledger is invite-only. Ask whoever sent you here to add your email.';

    protected function setUp(): void
    {
        parent::setUp();

        // uncompromised() calls the pwned-passwords API; an empty body means "not breached".
        Http::fake(['api.pwnedpasswords.com/*' => Http::response('')]);
    }

    /** @return array<string, string> */
    private function registration(string $email): array
    {
        return [
            'name' => 'Friend',
            'email' => $email,
            'password' => 'correct horse battery',
            'password_confirmation' => 'correct horse battery',
        ];
    }

    public function test_invite_adds_a_normalized_email(): void
    {
        $this->artisan('pesonal:invite', ['email' => ' Friend@Example.com '])
            ->expectsOutputToContain('Invited friend@example.com.')
            ->assertExitCode(0);

        $this->assertSame(['friend@example.com'], Invitation::pluck('email')->all());
    }

    public function test_inviting_twice_is_harmless(): void
    {
        $this->artisan('pesonal:invite', ['email' => 'friend@example.com'])->assertExitCode(0);

        $this->artisan('pesonal:invite', ['email' => 'Friend@Example.com'])
            ->expectsOutputToContain('friend@example.com was already invited.')
            ->assertExitCode(0);

        $this->assertSame(1, Invitation::count());
    }

    public function test_invite_rejects_a_non_email(): void
    {
        $this->artisan('pesonal:invite', ['email' => 'not-an-email'])->assertExitCode(1);

        $this->assertSame(0, Invitation::count());
    }

    public function test_invites_lists_emails_and_whether_they_opened_a_ledger(): void
    {
        $this->travelTo('2026-10-16 09:00:00');
        Invitation::create(['email' => 'friend@example.com']);
        Invitation::create(['email' => 'stranger@example.com']);
        User::factory()->create(['email' => 'friend@example.com']);

        $this->artisan('pesonal:invites')
            ->expectsTable(['Email', 'Invited', 'Has account'], [
                ['friend@example.com', '2026-10-16', 'yes'],
                ['stranger@example.com', '2026-10-16', 'no'],
            ])
            ->assertExitCode(0);
    }

    public function test_invites_says_so_when_the_list_is_empty(): void
    {
        $this->artisan('pesonal:invites')
            ->expectsOutputToContain('Nobody is invited yet.')
            ->assertExitCode(0);
    }

    public function test_uninvite_removes_the_email_but_not_the_account(): void
    {
        Invitation::create(['email' => 'friend@example.com']);
        $user = User::factory()->create(['email' => 'friend@example.com']);

        $this->artisan('pesonal:uninvite', ['email' => ' Friend@Example.com'])
            ->expectsOutputToContain('Removed friend@example.com from the list. An account they already opened keeps working.')
            ->assertExitCode(0);

        $this->assertSame(0, Invitation::count());
        $this->assertTrue($user->exists());
        $this->assertSame(1, User::count());

        $this->artisan('pesonal:uninvite', ['email' => 'friend@example.com'])
            ->expectsOutputToContain("friend@example.com isn't on the list.")
            ->assertExitCode(1);
    }

    public function test_registering_an_uninvited_email_is_refused(): void
    {
        // Someone else being invited must not let this email through.
        Invitation::create(['email' => 'friend@example.com']);

        $this->fromFrontend()
            ->postJson('/api/auth/register', $this->registration('stranger@example.com'))
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', self::NOT_INVITED_COPY);

        $this->assertSame(0, User::count());
        $this->assertGuest();
    }

    public function test_an_uninvited_refusal_does_not_say_the_email_has_an_account(): void
    {
        User::factory()->create(['email' => 'stranger@example.com']);

        $this->fromFrontend()
            ->postJson('/api/auth/register', $this->registration('stranger@example.com'))
            ->assertUnprocessable()
            ->assertJsonPath('errors.email', [self::NOT_INVITED_COPY]);
    }

    public function test_registering_an_invited_email_works_whatever_its_case(): void
    {
        $this->artisan('pesonal:invite', ['email' => ' Friend@Example.com '])->assertExitCode(0);

        $this->fromFrontend()
            ->postJson('/api/auth/register', $this->registration('friend@EXAMPLE.com'))
            ->assertCreated()
            ->assertJsonPath('data.email', 'friend@example.com');
    }

    public function test_a_revoked_invite_still_signs_in_its_account(): void
    {
        Invitation::create(['email' => 'friend@example.com']);
        $user = User::factory()->create(['email' => 'friend@example.com', 'password' => 'correct horse battery']);
        $user->social_accounts()->create(['provider' => 'google', 'provider_user_id' => 'g-1']);
        $this->artisan('pesonal:uninvite', ['email' => 'friend@example.com'])->assertExitCode(0);

        $this->fromFrontend()
            ->postJson('/api/auth/login', ['email' => 'friend@example.com', 'password' => 'correct horse battery'])
            ->assertOk()
            ->assertJsonPath('data.email', 'friend@example.com');

        $this->fromFrontend()->postJson('/api/auth/logout')->assertNoContent();
        $this->app['auth']->forgetGuards();
        $this->assertGuest();

        $attributes = ['id' => 'g-1', 'name' => 'Friend', 'email' => 'friend@example.com', 'avatar' => null, 'email_verified' => true];
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->andReturn((new SocialiteUser)->setRaw($attributes)->map($attributes));
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $this->get('/api/auth/google/callback')->assertRedirect('http://localhost:3000/');

        $this->assertAuthenticatedAs($user);
    }
}
