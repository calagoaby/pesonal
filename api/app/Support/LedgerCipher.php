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

    /** Plain is accepted only as digits (optionally a leading '-'): never read garbage as ₱0. */
    public static function decryptAmount(string|int $stored): int
    {
        if (is_int($stored)) {
            return $stored;
        }

        $plain = self::decrypt($stored);

        if (preg_match('/^-?\d+$/', $plain) !== 1) {
            throw new DecryptException('The stored amount is not an integer.');
        }

        return (int) $plain;
    }

    // Strict: base64 decoding to JSON with string iv/value/mac and a 16-byte iv
    // (AES-256-CBC), so ordinary text like "Gym" is never mistaken for a payload.
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

        if (isset($payload['tag']) && ! is_string($payload['tag'])) {
            return false;
        }

        $iv = base64_decode($payload['iv'], true);

        return $iv !== false && strlen($iv) === 16;
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
