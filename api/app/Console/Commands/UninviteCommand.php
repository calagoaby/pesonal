<?php

namespace App\Console\Commands;

use App\Models\Invitation;
use Illuminate\Console\Command;

class UninviteCommand extends Command
{
    protected $signature = 'pesonal:uninvite {email : The email to take off the sign-up list}';

    protected $description = 'Take an email off the sign-up list (an account it already opened keeps working)';

    public function handle(): int
    {
        $email = Invitation::normalize((string) $this->argument('email'));

        if (Invitation::where('email', $email)->delete() === 0) {
            $this->error("{$email} isn't on the list.");

            return self::FAILURE;
        }

        $this->info("Removed {$email} from the list. An account they already opened keeps working.");

        return self::SUCCESS;
    }
}
