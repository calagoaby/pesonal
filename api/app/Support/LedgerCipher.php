<?php

namespace App\Support;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

// Encrypts ledger values (amounts, notes, category names) with APP_KEY, so the
// database holds ciphertext. Reads tolerate a plain value: during a deploy the
// old container still writes plaintext until pesonal:encrypt-ledger catches up.
final class LedgerCipher
{
    private const FINGERPRINT_INFO = 'pesonal-category-name-index';

    private static ?string $fingerprint_source = null;

    private static string $fingerprint_key = '';

    public static function encrypt(string $plain): string
    {
        return Crypt::encryptString($plain);
    }

    /** A plain value is returned as-is; an encrypted payload whose MAC fails throws. */
    public static function decrypt(string $stored): string
    {
        return self::isEncrypted($stored) ? Crypt::decryptString($stored) : $stored;
    }

    /** Plain is accepted only as a canonical integer: never read garbage as ₱0. */
    public static function decryptAmount(string|int $stored): int
    {
        if (is_int($stored)) {
            return $stored;
        }

        $plain = self::decrypt($stored);

        // filter_var alone accepts '-0', '+5' and surrounding spaces; the regex rejects those,
        // and filter_var rejects overflow (and leading zeros).
        $amount = preg_match('/^(0|-?[1-9]\d*)$/', $plain) === 1 ? filter_var($plain, FILTER_VALIDATE_INT) : false;

        if ($amount === false) {
            throw new DecryptException('The stored amount is not an integer.');
        }

        return $amount;
    }

    // Base64 decoding to JSON with string iv/value/mac is a payload, so ordinary text
    // like "Gym" is never mistaken for one. Nothing stricter on purpose: a corrupted iv
    // or tag must reach the Encrypter and throw, not be read back as plain text.
    public static function isEncrypted(string $stored): bool
    {
        $json = base64_decode($stored, true);
        if ($json === false) {
            return false;
        }

        $payload = json_decode($json, true);
        if (! is_array($payload)) {
            return false;
        }

        foreach (['iv', 'value', 'mac'] as $field) {
            if (! isset($payload[$field]) || ! is_string($payload[$field])) {
                return false;
            }
        }

        return true;
    }

    // Case-sensitive on purpose: "Food" and "food" may coexist in one account today.
    // The user id is mixed in so the same name in two accounts gives different values.
    public static function fingerprint(int $userId, string $name): string
    {
        return hash_hmac('sha256', $userId.':'.trim($name), self::fingerprintKey());
    }

    // A separate key derived from APP_KEY, not APP_KEY itself. The encrypter's key is
    // already the decoded raw bytes (the 'base64:' prefix is handled by Laravel).
    private static function fingerprintKey(): string
    {
        $app_key = Crypt::getKey();

        if (self::$fingerprint_source !== $app_key) {
            self::$fingerprint_key = hash_hkdf('sha256', $app_key, 32, self::FINGERPRINT_INFO);
            self::$fingerprint_source = $app_key;
        }

        return self::$fingerprint_key;
    }
}
