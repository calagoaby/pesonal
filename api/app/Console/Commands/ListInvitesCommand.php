<?php

namespace App\Console\Commands;

use App\Models\Invitation;
use App\Models\User;
use Illuminate\Console\Command;

class ListInvitesCommand extends Command
{
    protected $signature = 'pesonal:invites';

    protected $description = 'List the sign-up list and which emails have opened an account';

    public function handle(): int
    {
        $invitations = Invitation::orderBy('email')->get();

        if ($invitations->isEmpty()) {
            $this->info('Nobody is invited yet.');

            return self::SUCCESS;
        }

        $withAccount = User::whereIn('email', $invitations->pluck('email'))->pluck('email')->flip();

        $this->table(
            ['Email', 'Invited', 'Has account'],
            $invitations->map(fn (Invitation $invitation) => [
                $invitation->email,
                $invitation->created_at?->toDateString(),
                $withAccount->has($invitation->email) ? 'yes' : 'no',
            ]),
        );

        return self::SUCCESS;
    }
}
