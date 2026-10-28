<?php

namespace App\Http\Resources;

use App\Models\Recurring;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Recurring */
class RecurringResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'amount' => $this->amount->centavos(), // centavos, bare int — wire stays a plain integer
            'note' => $this->note, // string | null, always emitted
            'items' => $this->items, // always an array, [] when none
            'day_of_month' => $this->day_of_month,
            'start_date' => $this->start_date->format('Y-m-d'),
            'end_date' => $this->end_date?->format('Y-m-d'), // null = open-ended
            'active' => $this->active, // real boolean
            'category' => CategoryRefResource::make($this->category),
            // Contract: ISO 8601 UTC with no fractional seconds — Laravel's default adds .000000.
            'created_at' => $this->created_at?->toIso8601ZuluString(),
            'updated_at' => $this->updated_at?->toIso8601ZuluString(),
        ];
    }
}
