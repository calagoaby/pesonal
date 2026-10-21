<?php

namespace App\Http\Resources;

use App\Models\Invitation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Invitation */
class InvitationResource extends JsonResource
{
    // Expects the row loaded through Invitation::withHasAccount().
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'invited_at' => $this->created_at?->toIso8601ZuluString(),
            // SQLite answers 0/1, Postgres a boolean.
            'has_account' => (bool) $this->getAttribute('has_account'),
        ];
    }
}
