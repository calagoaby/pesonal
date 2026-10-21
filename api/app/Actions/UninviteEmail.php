<?php

namespace App\Actions;

use App\Models\Invitation;

class UninviteEmail
{
    /** True when a row was removed. */
    public function execute(string $email): bool
    {
        return Invitation::where('email', Invitation::normalize($email))->delete() > 0;
    }
}
