<?php

namespace App\Console\Commands;

use App\Support\LedgerRewrite;
use Illuminate\Console\Command;

class EncryptLedgerCommand extends Command
{
    protected $signature = 'pesonal:encrypt-ledger';

    protected $description = 'Encrypt any plain ledger value and recompute every category name fingerprint';

    // Runs on every container boot: rows an older container wrote during a deploy are
    // encrypted here. Prints counts and ids only, never a value or a name.
    public function handle(): int
    {
        // Before any write: with a wrong APP_KEY the stragglers would be encrypted under it
        // and become unreadable along with everything else. This one fails the boot.
        if (! LedgerRewrite::keyReadsLedger()) {
            $this->error("APP_KEY can't read the existing ledger; nothing was changed.");

            return self::FAILURE;
        }

        $encrypted = LedgerRewrite::encrypt();
        // The key is known good now, so one corrupt name is reported, not fatal: it stays
        // loud on its own screen when read.
        $fingerprints = LedgerRewrite::fingerprint(skipUnreadable: true);

        $this->info("Encrypted {$encrypted['expenses']} expenses, {$encrypted['recurring']} standing orders, {$encrypted['categories']} categories.");
        $this->info("Fixed {$fingerprints['changed']} category name fingerprints.");

        foreach ($fingerprints['unreadable'] as $row) {
            $this->warn("Could not decrypt categories row {$row['id']} of user {$row['user_id']}; it was skipped.");
        }
        foreach ($fingerprints['collisions'] as $collision) {
            $this->warn("Could not update name_index for category ids {$collision['id']} and {$collision['other_id']} of user {$collision['user_id']}; same trimmed name?");
        }

        return self::SUCCESS;
    }
}
