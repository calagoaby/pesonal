<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsUser;
use Tests\TestCase;

// Postgres refuses to compare a bigint column with 'abc' (or a number past bigint), so an id
// that isn't one must be turned away before it reaches a query — as the resource's own 404.
class RouteIdTest extends TestCase
{
    use ActsAsUser;
    use RefreshDatabase;

    public function test_an_id_that_is_not_a_number_is_the_resources_404(): void
    {
        $cases = [
            'categories' => 'That category is gone.',
            'expenses' => 'That entry is not on the roll.',
            'recurring' => 'That standing order is gone.',
        ];

        foreach ($cases as $resource => $message) {
            foreach (['abc', '1abc', '-1', '99999999999999999999'] as $id) {
                $this->getJson("/api/{$resource}/{$id}")
                    ->assertNotFound()
                    ->assertJsonPath('message', $message);
            }
        }

        $this->postJson('/api/expenses/abc/restore')->assertNotFound()->assertJsonPath('message', 'That entry is not on the roll.');
        $this->postJson('/api/recurring/abc/restore')->assertNotFound()->assertJsonPath('message', 'That standing order is gone.');
    }
}
