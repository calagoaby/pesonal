<?php

namespace App\Actions\Fortify;

use App\Actions\CreateDefaultCategories;
use App\Models\Invitation;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function create(array $input): User
    {
        // Normalize before the checks so Aby@Example.com collides with aby@example.com
        // and matches an invitation for it.
        if (isset($input['email']) && is_string($input['email'])) {
            $input['email'] = Invitation::normalize($input['email']);
        }

        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            // bail + the invite check before unique: an uninvited visitor can't use the
            // form to learn which emails already have an account.
            'email' => ['bail', 'required', 'string', 'email', 'max:255', $this->invited(...), Rule::unique(User::class)],
            'password' => $this->passwordRules(),
        ])->validate();

        // One transaction, so an account never exists without its starting categories.
        return DB::transaction(function () use ($input) {
            $user = User::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => $input['password'],
            ]);

            app(CreateDefaultCategories::class)->execute($user);

            return $user;
        });
    }

    private function invited(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! Invitation::isInvited($value)) {
            $fail('This ledger is invite-only. Ask whoever sent you here to add your email.');
        }
    }
}
