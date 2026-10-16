<?php

namespace App\Actions;

use App\Exceptions\ProviderEmailMissing;
use App\Exceptions\ProviderEmailNotInvited;
use App\Exceptions\ProviderEmailUnverified;
use App\Models\Invitation;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Socialite\AbstractUser;
use Laravel\Socialite\Contracts\User as SocialUser;

class SignInWithProvider
{
    /**
     * Find or create the account a provider sign-in belongs to. The linking rules run in
     * order: already linked, link, take over, create. The caller signs the returned user in.
     *
     * @throws ProviderEmailMissing
     * @throws ProviderEmailNotInvited
     * @throws ProviderEmailUnverified
     */
    public function execute(string $provider, SocialUser $social): User
    {
        $providerUserId = (string) $social->getId();

        // Already linked: the (provider, id) pair decides, whatever the email says now.
        $linked = SocialAccount::query()
            ->where('provider', $provider)
            ->where('provider_user_id', $providerUserId)
            ->first();

        if ($linked !== null) {
            return $this->fillAvatar($linked->user()->firstOrFail(), $social);
        }

        $email = $social->getEmail();
        $email = is_string($email) ? Str::lower(trim($email)) : '';

        if ($email === '') {
            throw new ProviderEmailMissing;
        }

        if (! $this->emailIsVerified($provider, $social)) {
            throw new ProviderEmailUnverified;
        }

        return DB::transaction(function () use ($provider, $providerUserId, $email, $social) {
            $user = User::query()->where('email', $email)->first();

            // Link: a verified account with the email only gains the social row below.
            if ($user === null) {
                // Create: nobody has the email, so a new account, verified on arrival.
                // Sign-up is invite-only; the other branches reach an existing account.
                if (! Invitation::isInvited($email)) {
                    throw new ProviderEmailNotInvited;
                }

                $user = new User([
                    'name' => $social->getName() ?: Str::before($email, '@'),
                    'email' => $email,
                    'password' => null,
                ]);
                $user->email_verified_at = now();
                $user->save();

                app(CreateDefaultCategories::class)->execute($user);
            } elseif (! $user->hasVerifiedEmail()) {
                // Take over: whoever set its password never proved they own the email; the
                // provider just did. Otherwise someone could pre-register a victim's address
                // and share the account once the victim signs in.
                $user->email_verified_at = now();
                $user->password = null;
                $user->setRememberToken(Str::random(60));
                $user->save();

                // Only ends sessions under the database driver (today's driver).
                DB::table(config('session.table'))->where('user_id', $user->id)->delete();
            }

            $user->social_accounts()->create([
                'provider' => $provider,
                'provider_user_id' => $providerUserId,
            ]);

            return $this->fillAvatar($user, $social);
        });
    }

    // Facebook only ever sends confirmed emails. Google can send an address it hasn't
    // verified and says so in email_verified; a missing flag is treated as unverified.
    private function emailIsVerified(string $provider, SocialUser $social): bool
    {
        if ($provider !== 'google') {
            return true;
        }

        $raw = $social instanceof AbstractUser ? $social->getRaw() : [];

        return ($raw['email_verified'] ?? null) === true;
    }

    private function fillAvatar(User $user, SocialUser $social): User
    {
        $avatar = $social->getAvatar();

        if (empty($user->avatar_url) && is_string($avatar) && $avatar !== '') {
            $user->avatar_url = $avatar;
            $user->save();
        }

        return $user;
    }
}
