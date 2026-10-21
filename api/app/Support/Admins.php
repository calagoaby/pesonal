<?php

namespace App\Support;

use App\Models\Invitation;
use App\Models\User;

// The owner is whoever's email is in ADMIN_EMAILS, not a database flag: no migration, no way
// to promote yourself through the API, and taking it away is an .env edit plus a restart.
final class Admins
{
    /** @return list<string> */
    public static function emails(): array
    {
        $raw = config('pesonal.admin_emails');
        $emails = array_map(Invitation::normalize(...), explode(',', is_string($raw) ? $raw : ''));

        // Drop the blanks a trailing comma or an empty value leaves, so '' never matches anyone.
        return array_values(array_filter($emails, fn (string $email) => $email !== ''));
    }

    public static function isAdminEmail(string $email): bool
    {
        return in_array(Invitation::normalize($email), self::emails(), true);
    }

    // Verified too: anyone can register an unverified account under the owner's address.
    public static function isAdmin(?User $user): bool
    {
        return $user !== null && $user->hasVerifiedEmail() && self::isAdminEmail($user->email);
    }
}
