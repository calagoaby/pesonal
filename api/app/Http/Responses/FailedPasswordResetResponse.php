<?php

namespace App\Http\Responses;

use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\FailedPasswordResetResponse as FailedPasswordResetResponseContract;
use Symfony\Component\HttpFoundation\Response;

// One reply for every failure: an unknown email, a bad token and an expired token must be indistinguishable.
class FailedPasswordResetResponse implements FailedPasswordResetResponseContract
{
    // Fortify passes the failure status; it is deliberately ignored.
    public function __construct(protected ?string $status = null) {}

    public function toResponse($request): Response
    {
        throw ValidationException::withMessages([
            'email' => ['That reset slip is invalid or has expired.'],
        ]);
    }
}
