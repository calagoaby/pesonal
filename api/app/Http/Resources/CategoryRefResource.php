<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryRefResource extends JsonResource
{
    // The nested ref embedded in expense/recurring reads: four fields, no created_at.
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'color' => $this->color,
            'emoji' => $this->emoji,
        ];
    }
}