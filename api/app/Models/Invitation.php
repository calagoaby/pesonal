<?php

namespace App\Models;

use App\Support\Admins;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
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

    // An admin email counts as invited, so the owner can open the first account on a fresh install.
    public static function isInvited(string $email): bool
    {
        return Admins::isAdminEmail($email)
            || self::query()->where('email', self::normalize($email))->exists();
    }

    // has_account in the same query as the rows, instead of one lookup per invite.
    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeWithHasAccount(Builder $query): Builder
    {
        return $query->select('invitations.*')
            ->selectRaw('exists (select 1 from users where users.email = invitations.email) as has_account');
    }

    /** @return Attribute<string, string> */
    protected function email(): Attribute
    {
        return Attribute::set(fn (string $value) => self::normalize($value));
    }
}
