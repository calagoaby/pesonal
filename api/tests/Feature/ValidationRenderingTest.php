<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ValidationRenderingTest extends TestCase
{
    public function test_a_validation_error_keeps_the_contract_message_and_answers_422(): void
    {
        Route::middleware('api')->get('/api/_probe', fn () => throw ValidationException::withMessages(['note' => 'Nope.']));

        $this->getJson('/api/_probe')
            ->assertUnprocessable()
            ->assertExactJson(['message' => 'The given data was invalid.', 'errors' => ['note' => ['Nope.']]]);
    }

    // Fortify's LockoutResponse throws one with status 429.
    public function test_a_validation_error_with_its_own_status_keeps_it(): void
    {
        Route::middleware('api')->get('/api/_probe', fn () => throw ValidationException::withMessages(['email' => 'Slow down.'])->status(429));

        $this->getJson('/api/_probe')
            ->assertStatus(429)
            ->assertExactJson(['message' => 'The given data was invalid.', 'errors' => ['email' => ['Slow down.']]]);
    }
}
