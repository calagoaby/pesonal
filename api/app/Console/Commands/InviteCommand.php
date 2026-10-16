<?php

namespace App\Console\Commands;

use App\Models\Invitation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class InviteCommand extends Command
{
    protected $signature = 'pesonal:invite {email : The email allowed to open an account}';

    protected $description = 'Put an email on the sign-up list';

    public function handle(): int
    {
        $email = Invitation::normalize((string) $this->argument('email'));

        if (Validator::make(['email' => $email], ['email' => ['required', 'email', 'max:255']])->fails()) {
            $this->error("{$email} isn't an email address.");

            return self::FAILURE;
        }

        $invitation = Invitation::firstOrCreate(['email' => $email]);

        if (! $invitation->wasRecentlyCreated) {
            $this->info("{$email} was already invited.");

            return self::SUCCESS;
        }

        $this->info("Invited {$email}.");

        return self::SUCCESS;
    }
}
