<?php

namespace App\Http\Controllers\Admin;

use App\Actions\InviteEmail;
use App\Actions\InviteResult;
use App\Actions\UninviteEmail;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreInvitationRequest;
use App\Http\Resources\InvitationResource;
use App\Models\Invitation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class InvitationController extends Controller
{
    // A handful of rows: unpaginated, newest first (id breaks a same-second tie).
    public function index(): AnonymousResourceCollection
    {
        return InvitationResource::collection(
            Invitation::query()->withHasAccount()->latest()->orderByDesc('id')->get(),
        );
    }

    public function store(StoreInvitationRequest $request, InviteEmail $invite): JsonResponse
    {
        $email = Invitation::normalize($request->string('email')->toString());
        $result = $invite->execute($email);

        if ($result === InviteResult::NotAnEmail) {
            throw ValidationException::withMessages(['email' => 'The email field must be a valid email address.']);
        }

        $invitation = Invitation::query()->withHasAccount()->where('email', $email)->sole();

        return InvitationResource::make($invitation)
            ->response()
            ->setStatusCode($result === InviteResult::Invited ? 201 : 200);
    }

    // Same rule as pesonal:uninvite: the list loses the email, an account it opened stays.
    public function destroy(Invitation $invitation, UninviteEmail $uninvite): Response
    {
        $uninvite->execute($invitation->email);

        return response()->noContent();
    }
}
