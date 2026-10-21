<?php

namespace App\Http\Resources;

use App\Models\User;
use App\Support\Admins;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'avatar_url' => $this->avatar_url,
            'email_verified' => $this->email_verified_at !== null,
            'is_admin' => Admins::isAdmin($this->resource),
        ];
    }
}
