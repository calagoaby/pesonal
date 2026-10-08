<?php

namespace App\Http\Resources;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Category */
class CategoryResource extends JsonResource
{
    // The standalone category: five keys, no updated_at (the frontend Category type omits it).
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'color' => $this->color,
            'emoji' => $this->emoji,
            'created_at' => $this->created_at?->toIso8601ZuluString(), // contract: no fractional seconds
        ];
    }
}
