<?php

namespace App\Actions;

use App\Models\Invitation;
use Illuminate\Support\Facades\Validator;

class InviteEmail
{
    public function execute(string $email): InviteResult
    {
        $email = Invitation::normalize($email);

        if (Validator::make(['email' => $email], ['email' => ['required', 'email', 'max:255']])->fails()) {
            return InviteResult::NotAnEmail;
        }

        $invitation = Invitation::firstOrCreate(['email' => $email]);

        return $invitation->wasRecentlyCreated ? InviteResult::Invited : InviteResult::AlreadyInvited;
    }
}
