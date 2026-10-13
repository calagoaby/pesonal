<?php

namespace Tests\Concerns;

use App\Models\User;
use Laravel\Sanctum\Sanctum;

/**
 * Every data route needs a verified, signed-in user. Laravel's TestCase calls
 * setUp{TraitBasename}() on its own, so a test class only adds `use ActsAsUser;`.
 */
trait ActsAsUser
{
    protected User $user;

    protected function setUpActsAsUser(): void
    {
        $this->user = User::factory()->create();

        Sanctum::actingAs($this->user);
    }
}
