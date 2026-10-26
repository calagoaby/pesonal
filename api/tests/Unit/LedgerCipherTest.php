<?php

namespace Tests\Unit;

use App\Support\LedgerCipher;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class LedgerCipherTest extends TestCase
{
    public function test_round_trips_and_never_repeats(): void
    {
        $first = LedgerCipher::encrypt('15000');
        $second = LedgerCipher::encrypt('15000');

        $this->assertNotSame($first, $second);
        $this->assertSame('15000', LedgerCipher::decrypt($first));
        $this->assertSame('15000', LedgerCipher::decrypt($second));
        $this->assertTrue(LedgerCipher::isEncrypted($first));
        $this->assertSame(15000, LedgerCipher::decryptAmount($first));
    }

    public function test_plain_values_are_tolerated(): void
    {
        $this->assertFalse(LedgerCipher::isEncrypted('Gym'));
        $this->assertSame('Gym', LedgerCipher::decrypt('Gym'));
        $this->assertSame(15000, LedgerCipher::decryptAmount('15000'));
        $this->assertSame(15000, LedgerCipher::decryptAmount(15000));
        $this->assertSame(-500, LedgerCipher::decryptAmount('-500'));
    }

    public function test_garbage_amount_throws(): void
    {
        $payload = json_decode(base64_decode(LedgerCipher::encrypt('15000')), true);
        $payload['mac'] = str_repeat('0', 64);
        $tampered = base64_encode((string) json_encode($payload));

        $this->assertTrue(LedgerCipher::isEncrypted($tampered));
        $this->assertThrows(fn () => LedgerCipher::decryptAmount($tampered), DecryptException::class);
        $this->assertThrows(fn () => LedgerCipher::decrypt($tampered), DecryptException::class);
        $this->assertThrows(fn () => LedgerCipher::decryptAmount('12abc'), DecryptException::class);
        $this->assertThrows(fn () => LedgerCipher::decryptAmount(''), DecryptException::class);
        $this->assertThrows(fn () => LedgerCipher::decryptAmount(LedgerCipher::encrypt('Lunch')), DecryptException::class);
    }

    public function test_fingerprint_is_case_sensitive_per_user_and_trimmed(): void
    {
        $food = LedgerCipher::fingerprint(1, 'Food');

        $this->assertSame($food, LedgerCipher::fingerprint(1, ' Food '));
        $this->assertNotSame($food, LedgerCipher::fingerprint(1, 'food'));
        $this->assertNotSame($food, LedgerCipher::fingerprint(2, 'Food'));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $food);
    }

    public function test_a_tampered_payload_throws_instead_of_reading_as_plain(): void
    {
        foreach (['iv', 'value'] as $field) {
            $note = $this->tamper(LedgerCipher::encrypt('Lunch'), $field);
            $amount = $this->tamper(LedgerCipher::encrypt('15000'), $field);

            $this->assertTrue(LedgerCipher::isEncrypted($note));
            $this->assertThrows(fn () => LedgerCipher::decrypt($note), DecryptException::class);
            $this->assertThrows(fn () => LedgerCipher::decryptAmount($amount), DecryptException::class);
        }
    }

    public function test_a_gcm_payload_counts_as_encrypted_and_fails_to_decrypt(): void
    {
        $gcm = (new Encrypter(random_bytes(32), 'aes-256-gcm'))->encryptString('Lunch');

        $this->assertTrue(LedgerCipher::isEncrypted($gcm));
        $this->assertThrows(fn () => LedgerCipher::decrypt($gcm), DecryptException::class);
    }

    public function test_only_canonical_plain_integers_are_amounts(): void
    {
        $this->assertSame(0, LedgerCipher::decryptAmount('0'));

        foreach (['-0', '007', '+5', ' 5', '5 ', '1.5', '99999999999999999999'] as $plain) {
            $this->assertThrows(fn () => LedgerCipher::decryptAmount($plain), DecryptException::class);
        }
    }

    public function test_fingerprint_changes_with_the_app_key(): void
    {
        $before = LedgerCipher::fingerprint(1, 'Food');

        Crypt::swap(new Encrypter(random_bytes(32), 'AES-256-CBC'));

        $this->assertNotSame($before, LedgerCipher::fingerprint(1, 'Food'));
    }

    private function tamper(string $encrypted, string $field): string
    {
        $payload = json_decode(base64_decode($encrypted), true);
        $payload[$field] = base64_encode('not the original bytes');

        return base64_encode((string) json_encode($payload));
    }
}
