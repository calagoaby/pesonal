<?php

namespace App\Rules;

use App\Support\LedgerCipher;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;

// Rule::unique can't compare an encrypted name (a fresh IV every time), so this looks
// the fingerprint up instead, within the one account. Case-sensitive, as before.
final class UniqueCategoryName implements ValidationRule
{
    public function __construct(private int $userId, private ?int $ignoreId = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        $taken = DB::table('categories')
            ->where('user_id', $this->userId)
            ->where('name_index', LedgerCipher::fingerprint($this->userId, $value))
            ->when($this->ignoreId !== null, fn ($query) => $query->where('id', '!=', $this->ignoreId))
            ->exists();

        if ($taken) {
            // The framework's own unique message, so the 422 reads exactly as before.
            $fail('validation.unique')->translate();
        }
    }
}
