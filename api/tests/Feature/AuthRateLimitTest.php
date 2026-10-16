<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AuthRateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake(['api.pwnedpasswords.com/*' => Http::response('')]);
    }

    public function test_register_is_throttled_per_ip(): void
    {
        for ($i = 1; $i <= 6; $i++) {
            $this->fromFrontend()->postJson('/api/auth/register', ['email' => "nobody{$i}@example.com"])
                ->assertStatus(422);
        }

        $this->fromFrontend()->postJson('/api/auth/register', ['email' => 'nobody7@example.com'])
            ->assertStatus(429);
    }

    public function test_forgot_password_is_throttled_per_ip(): void
    {
        for ($i = 1; $i <= 6; $i++) {
            $this->fromFrontend()->postJson('/api/auth/forgot-password', ['email' => "nobody{$i}@example.com"])
                ->assertOk();
        }

        $this->fromFrontend()->postJson('/api/auth/forgot-password', ['email' => 'nobody7@example.com'])
            ->assertStatus(429);
    }

    public function test_the_limiter_is_attached_to_register_and_forgot_password(): void
    {
        foreach (['register.store', 'password.email'] as $name) {
            $this->assertContains('throttle:auth-mail', Route::getRoutes()->getByName($name)->gatherMiddleware(), $name);
        }
    }
}
