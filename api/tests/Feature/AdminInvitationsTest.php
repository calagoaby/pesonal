<?php

namespace Tests\Feature;

use App\Models\Invitation;
use App\Models\User;
use App\Support\Admins;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class AdminInvitationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Padded and mixed-case on purpose: the list is normalized before it's compared.
        config(['pesonal.admin_emails' => 'Boss@Example.com ']);
        Http::fake(['api.pwnedpasswords.com/*' => Http::response('')]);
    }

    private function admin(): User
    {
        return User::factory()->create(['email' => 'boss@example.com']);
    }

    // What the API answers for a route that doesn't exist, with the path swapped in. Debug off,
    // as in production: debug mode adds a stack trace that differs per call site.
    private function missingRouteBody(string $path): string
    {
        $content = (string) $this->getJson('/api/nope')->assertNotFound()->getContent();

        return str_replace('api/nope', $path, $content);
    }

    private function assertLooksMissing(TestResponse $response, string $path): void
    {
        $response->assertNotFound();
        $this->assertSame($this->missingRouteBody($path), $response->getContent());
    }

    public function test_admin_lists_adds_and_removes_invites(): void
    {
        $this->travelTo('2026-10-20 08:00:00');
        $old = Invitation::create(['email' => 'friend@example.com']);
        User::factory()->create(['email' => 'friend@example.com']);
        $this->travelTo('2026-10-21 09:30:00');
        Sanctum::actingAs($this->admin());

        $this->getJson('/api/admin/invitations')
            ->assertOk()
            ->assertExactJson(['data' => [
                ['id' => $old->id, 'email' => 'friend@example.com', 'invited_at' => '2026-10-20T08:00:00Z', 'has_account' => true],
            ]]);

        $created = $this->fromFrontend()->postJson('/api/admin/invitations', ['email' => ' New@Example.com '])
            ->assertCreated()
            ->assertJsonPath('data.email', 'new@example.com')
            ->assertJsonPath('data.invited_at', '2026-10-21T09:30:00Z')
            ->assertJsonPath('data.has_account', false);
        $id = $created->json('data.id');

        $this->fromFrontend()->postJson('/api/admin/invitations', ['email' => 'new@example.com'])
            ->assertOk()
            ->assertJsonPath('data.id', $id);
        $this->assertSame(1, Invitation::where('email', 'new@example.com')->count());

        // Newest first.
        $this->assertSame(
            ['new@example.com', 'friend@example.com'],
            array_column($this->getJson('/api/admin/invitations')->json('data'), 'email'),
        );

        $this->fromFrontend()->deleteJson("/api/admin/invitations/{$id}")->assertNoContent();

        $this->assertSame(
            ['friend@example.com'],
            array_column($this->getJson('/api/admin/invitations')->json('data'), 'email'),
        );
    }

    public function test_an_unknown_id_is_a_404_for_the_admin(): void
    {
        Sanctum::actingAs($this->admin());

        $this->fromFrontend()->deleteJson('/api/admin/invitations/999')
            ->assertNotFound()
            ->assertJsonPath('message', 'That invite is gone.');
    }

    public function test_a_malformed_email_is_a_field_error(): void
    {
        Sanctum::actingAs($this->admin());

        $this->fromFrontend()->postJson('/api/admin/invitations', ['email' => 'not-an-email'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'The given data was invalid.')
            ->assertJsonValidationErrors(['email']);

        $this->fromFrontend()->postJson('/api/admin/invitations', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        $this->assertSame(0, Invitation::count());
    }

    public function test_non_admins_get_404_everywhere(): void
    {
        config(['app.debug' => false]);
        $invitation = Invitation::create(['email' => 'friend@example.com']);

        $callers = [
            'signed out' => null,
            'unverified admin email' => User::factory()->unverified()->create(['email' => 'boss@example.com']),
            'verified non-admin' => User::factory()->create(['email' => 'friend@example.com']),
        ];

        foreach ($callers as $label => $user) {
            $this->app['auth']->forgetGuards();
            if ($user !== null) {
                Sanctum::actingAs($user);
            }

            $this->assertLooksMissing($this->getJson('/api/admin/invitations'), 'api/admin/invitations');
            $this->assertLooksMissing(
                $this->fromFrontend()->postJson('/api/admin/invitations', ['email' => 'x@example.com']),
                'api/admin/invitations',
            );
            // An id that exists and one that doesn't answer alike: the check runs before binding.
            $this->assertLooksMissing(
                $this->fromFrontend()->deleteJson("/api/admin/invitations/{$invitation->id}"),
                "api/admin/invitations/{$invitation->id}",
            );
            $this->assertLooksMissing(
                $this->fromFrontend()->deleteJson('/api/admin/invitations/999'),
                'api/admin/invitations/999',
            );
        }

        $this->assertSame(['friend@example.com'], Invitation::pluck('email')->all(), 'nothing changed');
    }

    public function test_a_non_admin_is_never_throttled_into_a_429(): void
    {
        foreach (range(1, 31) as $_) {
            $this->getJson('/api/admin/invitations')->assertNotFound();
        }
    }

    public function test_removing_an_invite_keeps_the_account(): void
    {
        $invitation = Invitation::create(['email' => 'friend@example.com']);
        User::factory()->create(['email' => 'friend@example.com', 'password' => 'correct horse battery']);
        Sanctum::actingAs($this->admin());

        $this->fromFrontend()->deleteJson("/api/admin/invitations/{$invitation->id}")->assertNoContent();
        $this->assertSame(0, Invitation::count());

        $this->app['auth']->forgetGuards();
        $this->fromFrontend()
            ->postJson('/api/auth/login', ['email' => 'friend@example.com', 'password' => 'correct horse battery'])
            ->assertOk()
            ->assertJsonPath('data.email', 'friend@example.com');
    }

    public function test_inviting_your_own_admin_email_is_harmless(): void
    {
        Sanctum::actingAs($this->admin());

        $response = $this->fromFrontend()->postJson('/api/admin/invitations', ['email' => 'Boss@Example.com']);

        $this->assertContains($response->status(), [200, 201]);
        $response->assertJsonPath('data.email', 'boss@example.com')
            ->assertJsonPath('data.has_account', true);
    }

    public function test_admin_emails_parsing(): void
    {
        $cases = [
            '' => [],
            ' , ' => [],
            'a@x.com,' => ['a@x.com'],
            'A@x.com , b@x.com' => ['a@x.com', 'b@x.com'],
        ];

        foreach ($cases as $raw => $expected) {
            config(['pesonal.admin_emails' => $raw]);
            $this->assertSame($expected, Admins::emails(), "ADMIN_EMAILS='{$raw}'");
        }

        config(['pesonal.admin_emails' => ' , ']);
        $this->assertFalse(Admins::isAdminEmail(''));
        $this->assertFalse(Admins::isAdmin(new User(['email' => ''])));
        $this->assertFalse(Admins::isAdmin(null));
    }

    public function test_is_admin_on_the_user(): void
    {
        $admin = $this->admin();
        $friend = User::factory()->create(['email' => 'friend@example.com']);
        $unverified = User::factory()->unverified()->create(['email' => 'boss2@example.com']);
        config(['pesonal.admin_emails' => 'boss@example.com,boss2@example.com']);

        Sanctum::actingAs($admin);
        $this->getJson('/api/user')->assertOk()->assertJsonPath('data.is_admin', true);

        Sanctum::actingAs($friend);
        $this->getJson('/api/user')->assertOk()->assertJsonPath('data.is_admin', false);

        Sanctum::actingAs($unverified);
        $this->getJson('/api/user')->assertOk()->assertJsonPath('data.is_admin', false);

        config(['pesonal.admin_emails' => '']);
        Sanctum::actingAs($admin);
        $this->getJson('/api/user')->assertOk()->assertJsonPath('data.is_admin', false);
    }

    public function test_an_admin_email_registers_without_an_invite(): void
    {
        $this->fromFrontend()->postJson('/api/auth/register', [
            'name' => 'Boss',
            'email' => 'Boss@Example.com',
            'password' => 'correct horse battery',
            'password_confirmation' => 'correct horse battery',
        ])->assertCreated()->assertJsonPath('data.email', 'boss@example.com');

        $this->assertSame(0, Invitation::count());
    }

    public function test_an_admin_email_signs_in_with_google_for_the_first_time_without_an_invite(): void
    {
        config(['services.google.client_id' => 'test-google-id']);
        $attributes = ['id' => 'g-1', 'name' => 'Boss', 'email' => 'boss@example.com', 'avatar' => null, 'email_verified' => true];
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->andReturn((new SocialiteUser)->setRaw($attributes)->map($attributes));
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $this->get('/api/auth/google/callback')->assertRedirect('http://localhost:3000/');

        $this->assertSame('boss@example.com', User::sole()->email);
        $this->assertSame(0, Invitation::count());
    }
}
