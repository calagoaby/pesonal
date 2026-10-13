<?php

namespace App\Http\Responses;

use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;
use Symfony\Component\HttpFoundation\Response;

class RegisterResponse implements RegisterResponseContract
{
    public function toResponse($request): Response
    {
        /** @var Request $request */
        return (new UserResource($request->user()))->response()->setStatusCode(201);
    }
}
