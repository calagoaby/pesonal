<?php

namespace Tests\Unit;

use App\Support\LedgerCipher;
use Illuminate\Contracts\Encryption\DecryptException;
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
}
