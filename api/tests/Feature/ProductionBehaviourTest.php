<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\Invitation;
use App\Models\Recurring;
use App\Models\Scopes\OwnedByCurrentUser;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class ProductionBehaviourTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 's3cret';

    protected function setUp(): void
    {
        parent::setUp();

        // uncompromised() calls the pwned-passwords API; an empty body means "not breached".
        Http::fake(['api.pwnedpasswords.com/*' => Http::response('')]);
    }

    /** @return array<string, string> */
    private function proxied(): array
    {
        return ['X-Pesonal-Proxy' => self::SECRET];
    }

    // The array driver never writes a row; the database driver is what production's session
    // store does, so "no row" means the gate stopped the request before StartSession. Only the
    // gate tests use it: the test client reuses one session Store, which keeps a signed-in
    // attribute across requests that the array driver's flush-free tests never notice.
    private function useDatabaseSessions(): void
    {
        config(['session.driver' => 'database']);
    }

    private function assertNoCookies(TestResponse $response): void
    {
        $this->assertFalse($response->headers->has('Set-Cookie'), 'The gate answered with a Set-Cookie header.');
        $this->assertSame([], $response->headers->getCookies());
    }

    private function register(string $email = 'aby@example.com'): TestResponse
    {
        Invitation::create(['email' => $email]);

        return $this->postJson('/api/auth/register', [
            'name' => 'Aby',
            'email' => $email,
            'password' => 'correct horse battery',
            'password_confirmation' => 'correct horse battery',
        ]);
    }

    public function test_without_the_secret_every_path_but_up_is_404_before_the_session(): void
    {
        // Debug off, as in production: debug adds a stack trace that differs per call site.
        config(['app.debug' => false]);
        $missing = (string) $this->getJson('/api/nope')->assertNotFound()->getContent();
        $this->useDatabaseSessions();
        config(['pesonal.proxy_secret' => self::SECRET]);

        // Neon sleeps after 5 idle minutes: a stranger's request must not run a single query.
        $queries = 0;
        DB::listen(function () use (&$queries) {
            $queries++;
        });

        $responses = [
            'api/categories' => $this->fromFrontend()->getJson('/api/categories'),
            'api/auth/login' => $this->fromFrontend()->postJson('/api/auth/login', ['email' => 'a@example.com', 'password' => 'x']),
            'sanctum/csrf-cookie' => $this->fromFrontend()->getJson('/sanctum/csrf-cookie'),
            'api/auth/google/redirect' => $this->get('/api/auth/google/redirect'),
        ];

        foreach ($responses as $path => $response) {
            $response->assertNotFound();
            $this->assertNoCookies($response);
        }
        // The same body as a route that doesn't exist, path swapped in.
        $this->assertSame(str_replace('api/nope', 'api/categories', $missing), $responses['api/categories']->getContent());
        // A wrong secret is no secret.
        $this->withHeaders(['X-Pesonal-Proxy' => 'guess'])->fromFrontend()->getJson('/api/categories')->assertNotFound();

        $up = $this->get('/up');
        $up->assertOk();
        $this->assertNoCookies($up);
        $this->assertSame(0, $queries);
        $this->assertDatabaseCount('sessions', 0);
        // The listener's control: that count was a query, and it was seen.
        $this->assertGreaterThan(0, $queries);
    }

    public function test_with_the_secret_requests_pass(): void
    {
        config(['pesonal.proxy_secret' => self::SECRET]);
        $this->useDatabaseSessions();
        $user = User::factory()->create(['password' => 'correct horse battery']);

        Sanctum::actingAs(User::factory()->create());
        $this->withHeaders($this->proxied())->fromFrontend()->getJson('/api/categories')->assertOk();
        $this->app['auth']->forgetGuards();

        $csrf = $this->withHeaders($this->proxied())->fromFrontend()->get('/sanctum/csrf-cookie');
        $csrf->assertNoContent();
        $this->assertNotSame([], $csrf->headers->getCookies());
        // The control for the test above: the session store really does write a row here.
        $this->assertDatabaseCount('sessions', 1);

        $this->withHeaders($this->proxied())->fromFrontend()
            ->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'correct horse battery'])
            ->assertOk();
    }

    public function test_the_gate_is_off_when_no_secret_is_set(): void
    {
        $this->assertSame('', config('pesonal.proxy_secret'));

        $this->fromFrontend()->get('/sanctum/csrf-cookie')->assertNoContent();
        $this->fromFrontend()->getJson('/api/categories')->assertUnauthorized();
    }

    public function test_forwarded_headers_are_trusted_only_with_the_secret(): void
    {
        $forwardedFrom = fn (string $ip) => [
            'X-Forwarded-For' => $ip,
            'X-Forwarded-Host' => 'pesonal.example',
            'X-Forwarded-Proto' => 'https',
        ];

        // Gate off (local): forwarded headers are ignored, so every request is 127.0.0.1 and
        // the shared auth-mail budget (6/min) runs out whatever X-Forwarded-For says.
        for ($i = 0; $i < 6; $i++) {
            $this->withHeaders($forwardedFrom('203.0.113.9'))->postJson('/api/auth/forgot-password', ['email' => 'x@example.com'])->assertOk();
        }
        $this->withHeaders($forwardedFrom('203.0.113.10'))->postJson('/api/auth/forgot-password', ['email' => 'x@example.com'])->assertTooManyRequests();

        // Gate on: the limiter keys on the forwarded client ip.
        $this->flushHeaders();
        config(['pesonal.proxy_secret' => self::SECRET]);
        $this->travel(2)->minutes();
        for ($i = 0; $i < 6; $i++) {
            $this->withHeaders($this->proxied() + $forwardedFrom('203.0.113.9'))->postJson('/api/auth/forgot-password', ['email' => 'x@example.com'])->assertOk();
        }
        $this->withHeaders($this->proxied() + $forwardedFrom('203.0.113.9'))->postJson('/api/auth/forgot-password', ['email' => 'x@example.com'])->assertTooManyRequests();
        $this->withHeaders($this->proxied() + $forwardedFrom('203.0.113.10'))->postJson('/api/auth/forgot-password', ['email' => 'x@example.com'])->assertOk();

        // Without the secret the request never gets that far: no account, no mail.
        $this->travel(2)->minutes();
        $this->flushHeaders();
        $this->withHeaders($forwardedFrom('203.0.113.9'))->fromFrontend();
        $this->register('stranger@example.com')->assertNotFound();
        $this->assertDatabaseMissing('users', ['email' => 'stranger@example.com']);

        // With it, the verify link is built on the forwarded scheme and host, and only those: the
        // proxy passes the browser's headers on, so a sent prefix or port must not shape the link.
        $this->withHeaders($this->proxied() + $forwardedFrom('203.0.113.9') + ['X-Forwarded-Prefix' => '/evil', 'X-Forwarded-Port' => '1234'])->fromFrontend();
        $this->register()->assertCreated();

        $transport = Mail::mailer('array')->getSymfonyTransport();
        $this->assertInstanceOf(ArrayTransport::class, $transport);
        $messages = $transport->messages();
        $this->assertCount(1, $messages);
        $email = $messages[0]->getOriginalMessage();
        $this->assertInstanceOf(Email::class, $email);
        $this->assertMatchesRegularExpression(
            '#href="https://pesonal\.example/api/auth/email/verify/\d+/[0-9a-f]+\?expires=#',
            (string) $email->getHtmlBody(),
        );
    }

    public function test_daily_job_runs_on_manila_today(): void
    {
        $owner = User::factory()->create();
        $first = Recurring::factory()->create(['user_id' => $owner->id, 'day_of_month' => 1, 'start_date' => '2026-01-01']);
        $last = Recurring::factory()->create(['user_id' => $owner->id, 'day_of_month' => 31, 'start_date' => '2026-01-01']);

        // October is charged on the morning of the 31st, Manila time.
        $this->travelTo('2026-10-31 01:00:00');
        $this->artisan('recurring:materialize')->assertSuccessful();

        // 16:05 UTC on the 31st is 00:05 on November 1st in Manila.
        $this->travelTo('2026-10-31 16:05:00');
        $this->artisan('recurring:materialize')->assertSuccessful();

        $expenses = Expense::withoutGlobalScope(OwnedByCurrentUser::class);
        $this->assertSame(['2026-10-01', '2026-11-01'], (clone $expenses)->where('recurring_id', $first->id)->orderBy('date')->get()->map(fn (Expense $e) => $e->date->format('Y-m-d'))->all());
        $this->assertSame(['2026-10-31'], (clone $expenses)->where('recurring_id', $last->id)->get()->map(fn (Expense $e) => $e->date->format('Y-m-d'))->all());
    }

    public function test_schedule_is_daily_in_production_and_hourly_locally(): void
    {
        $this->assertSame('0 * * * *', $this->materializeEvent()->expression);

        $this->app->detectEnvironment(fn () => 'production');
        $this->app->forgetInstance(Schedule::class);
        Facade::clearResolvedInstances();
        require base_path('routes/console.php');

        $event = $this->materializeEvent();
        $this->assertSame('5 0 * * *', $event->expression);
        $this->assertSame('Asia/Manila', $event->timezone);
    }

    private function materializeEvent(): Event
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn (Event $event) => str_contains((string) $event->command, 'recurring:materialize'));
        $this->assertInstanceOf(Event::class, $event);

        return $event;
    }

    public function test_a_mail_failure_never_breaks_sign_up(): void
    {
        Exceptions::fake();
        // A real send that fails, the way Resend refuses a recipient before a domain is verified.
        Mail::extend('exploding', fn () => new class extends AbstractTransport
        {
            protected function doSend(SentMessage $message): void
            {
                throw new TransportException('Resend says no.');
            }

            public function __toString(): string
            {
                return 'exploding://';
            }
        });
        config(['mail.mailers.exploding' => ['transport' => 'exploding'], 'mail.default' => 'exploding']);

        $this->fromFrontend()->register()->assertCreated();
        $user = User::where('email', 'aby@example.com')->firstOrFail();
        Exceptions::assertReported(TransportException::class);
        // Signed out again, or forgot-password's guest middleware redirects.
        $this->app['auth']->forgetGuards();
        $this->app['session.store']->flush();

        $this->fromFrontend()->postJson('/api/auth/forgot-password', ['email' => $user->email])
            ->assertOk()
            ->assertExactJson(['message' => "If it's on file, a reset slip is on its way."]);

        $this->fromFrontend()->actingAs($user)->postJson('/api/auth/email/verification-notification')->assertStatus(202);

        Exceptions::assertReportedCount(3);
    }

    public function test_a_mail_bug_that_is_not_a_transport_failure_still_throws(): void
    {
        $user = User::factory()->unverified()->create();
        VerifyEmail::createUrlUsing(fn () => throw new RuntimeException('No route to build the link.'));

        try {
            $this->expectException(RuntimeException::class);
            $user->sendEmailVerificationNotification();
        } finally {
            // A static: left set, it would break every later test's verify mail.
            VerifyEmail::$createUrlCallback = null;
        }
    }
}
