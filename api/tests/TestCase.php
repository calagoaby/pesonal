<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    // Sanctum only starts a session for requests from a stateful frontend; without these headers
    // login "succeeds" but nothing is signed in.
    public function fromFrontend(): static
    {
        return $this->withHeaders([
            'Origin' => 'http://localhost:3000',
            'Referer' => 'http://localhost:3000',
        ]);
    }
}
