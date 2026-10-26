<?php

namespace Tests\Concerns;

/**
 * For tests about rows from before accounts existed, which the require_user_id
 * migration forbids: it's rolled back first, and with it the encryption migration
 * that runs later (those rows are plaintext, with no name fingerprint). Pair with
 * DatabaseMigrations, not RefreshDatabase; rolling back inside its wrapping
 * transaction is unreliable.
 */
trait AllowsUnownedRows
{
    // A fresh test database runs every migration in one batch, so a bare --step 1 would
    // undo whichever sorts last. --path narrows the last batch to these files.
    protected const REQUIRE_USER_ID_MIGRATION = 'database/migrations/2026_10_13_000004_require_user_id_on_ledger_tables.php';

    protected const ENCRYPT_LEDGER_MIGRATION = 'database/migrations/2026_10_26_000001_encrypt_ledger_fields.php';

    protected function setUpAllowsUnownedRows(): void
    {
        $this->artisan('migrate:rollback', ['--path' => [self::ENCRYPT_LEDGER_MIGRATION, self::REQUIRE_USER_ID_MIGRATION]])->assertSuccessful();
    }
}
