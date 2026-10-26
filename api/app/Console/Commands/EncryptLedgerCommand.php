<?php

namespace App\Console\Commands;

use App\Support\LedgerRewrite;
use Illuminate\Console\Command;

class EncryptLedgerCommand extends Command
{
    protected $signature = 'pesonal:encrypt-ledger';

    protected $description = 'Encrypt any plain ledger value and recompute every category name fingerprint';

    // Runs on every container boot: rows an older container wrote during a deploy are
    // encrypted here. Prints counts only, never a value, and never fails the boot over a
    // duplicate name.
    public function handle(): int
    {
        $encrypted = LedgerRewrite::encrypt();
        $fingerprints = LedgerRewrite::fingerprint();

        $this->info("Encrypted {$encrypted['expenses']} expenses, {$encrypted['recurring']} standing orders, {$encrypted['categories']} categories.");
        $this->info("Fixed {$fingerprints['changed']} category name fingerprints.");

        // Ids only: the names are private.
        foreach ($fingerprints['collisions'] as $collision) {
            $this->warn("Categories {$collision['id']} and {$collision['other_id']} of user {$collision['user_id']} now have the same name; category {$collision['id']} keeps its old fingerprint until one is renamed.");
        }

        return self::SUCCESS;
    }
}
