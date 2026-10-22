<?php

namespace App\Models;

use App\Notifications\QueuedResetPassword;
use App\Notifications\QueuedVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Notifications\Notification;
use Laravel\Sanctum\HasApiTokens;
use Throwable;

#[Fillable(['name', 'email', 'password', 'avatar_url'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notifyOrReport(new QueuedVerifyEmail);
    }

    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->notifyOrReport(new QueuedResetPassword($token));
    }

    // Production runs the queue sync, so a refused send (Resend before a domain is verified)
    // throws right here, inside register / forgot-password / resend. The account or the request
    // still stands; the failure goes to the log instead of turning the reply into a 500.
    private function notifyOrReport(Notification $notification): void
    {
        try {
            $this->notify($notification);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /** @return HasMany<SocialAccount, $this> */
    public function social_accounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }

    /** @return Attribute<string, string> */
    protected function email(): Attribute
    {
        return Attribute::set(fn (string $value) => strtolower($value));
    }
}
