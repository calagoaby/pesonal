<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\ApiFormRequest;

// Shape only. Whether it's a real email is InviteEmail's call, the same rule the
// pesonal:invite command uses.
class StoreInvitationRequest extends ApiFormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string'],
        ];
    }
}
