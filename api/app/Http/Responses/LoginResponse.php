<?php

namespace App\Http\Responses;

use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

class LoginResponse implements LoginResponseContract
{
    public function toResponse($request): Response
    {
        /** @var Request $request */
        return (new UserResource($request->user()))->response()->setStatusCode(200);
    }
}
