<?php

namespace App\Console\Commands;

use App\Actions\InviteEmail;
use App\Actions\InviteResult;
use App\Models\Invitation;
use Illuminate\Console\Command;

class InviteCommand extends Command
{
    protected $signature = 'pesonal:invite {email : The email allowed to open an account}';

    protected $description = 'Put an email on the sign-up list';

    public function handle(InviteEmail $inviteEmail): int
    {
        $email = Invitation::normalize((string) $this->argument('email'));

        $result = $inviteEmail->execute($email);

        if ($result === InviteResult::NotAnEmail) {
            $this->error("{$email} isn't an email address.");

            return self::FAILURE;
        }

        if ($result === InviteResult::AlreadyInvited) {
            $this->info("{$email} was already invited.");

            return self::SUCCESS;
        }

        $this->info("Invited {$email}.");

        return self::SUCCESS;
    }
}
