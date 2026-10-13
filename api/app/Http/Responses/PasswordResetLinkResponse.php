<?php

namespace App\Http\Responses;

use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;
use Symfony\Component\HttpFoundation\Response;

// Bound to both contracts so a known and an unknown email get the identical reply (no account probing).
class PasswordResetLinkResponse implements FailedPasswordResetLinkRequestResponse, SuccessfulPasswordResetLinkRequestResponse
{
    // Fortify builds the failed response with a `status` argument; it is deliberately ignored.
    public function __construct(protected ?string $status = null) {}

    public function toResponse($request): Response
    {
        return response()->json(['message' => "If it's on file, a reset slip is on its way."], 200);
    }
}
