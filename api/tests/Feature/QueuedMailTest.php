<?php

namespace Tests\Feature;

use App\Models\Invitation;
use App\Models\User;
use App\Notifications\QueuedResetPassword;
use App\Notifications\QueuedVerifyEmail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class QueuedMailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // uncompromised() calls the pwned-passwords API; an empty body means "not breached".
        Http::fake(['api.pwnedpasswords.com/*' => Http::response('')]);
    }

    public function test_registration_queues_the_verification_mail(): void
    {
        Notification::fake();
        Invitation::create(['email' => 'aby@example.com']);

        $this->fromFrontend()->postJson('/api/auth/register', [
            'name' => 'Aby',
            'email' => 'aby@example.com',
            'password' => 'correct horse battery',
            'password_confirmation' => 'correct horse battery',
        ])->assertCreated();

        $user = User::where('email', 'aby@example.com')->firstOrFail();
        Notification::assertSentTo($user, QueuedVerifyEmail::class, fn ($notification) => $notification instanceof ShouldQueue);
    }

    public function test_forgot_password_queues_the_reset_mail(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->fromFrontend()->postJson('/api/auth/forgot-password', ['email' => $user->email])->assertOk();

        Notification::assertSentTo($user, QueuedResetPassword::class, fn ($notification) => $notification instanceof ShouldQueue);
    }

    public function test_the_queued_verification_link_verifies(): void
    {
        $this->assertSame('sync', config('queue.default'));
        Notification::fake();
        $user = User::factory()->unverified()->create();

        $user->sendEmailVerificationNotification();

        $url = null;
        Notification::assertSentTo($user, QueuedVerifyEmail::class, function (QueuedVerifyEmail $notification) use ($user, &$url) {
            $mail = $notification->toMail($user);
            $this->assertInstanceOf(MailMessage::class, $mail);
            $url = $mail->actionUrl;

            return true;
        });

        $this->assertSame(parse_url((string) config('app.url'), PHP_URL_HOST), parse_url((string) $url, PHP_URL_HOST));

        $this->fromFrontend()->actingAs($user)->get($url)->assertRedirectContains('verified=1');
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }
}
