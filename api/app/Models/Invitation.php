<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

// The sign-up allowlist: only an email on it can open a new account. Signing in to an
// account that already exists never looks here, so removing an email locks nobody out.
#[Fillable(['email'])]
class Invitation extends Model
{
    public static function normalize(string $email): string
    {
        return Str::lower(trim($email));
    }

    public static function isInvited(string $email): bool
    {
        return self::query()->where('email', self::normalize($email))->exists();
    }

    /** @return Attribute<string, string> */
    protected function email(): Attribute
    {
        return Attribute::set(fn (string $value) => self::normalize($value));
    }
}
