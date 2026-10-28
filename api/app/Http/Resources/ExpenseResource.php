<?php

namespace App\Http\Resources;

use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Expense */
class ExpenseResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'amount' => $this->amount->centavos(), // centavos, bare int — wire stays a plain integer
            'date' => $this->date->format('Y-m-d'), // calendar date, no time
            'note' => $this->note,
            'items' => $this->items, // always an array, [] when none
            'category' => CategoryRefResource::make($this->category), // 4-field ref, never category_id
            'recurring_id' => $this->recurring_id,
            // Contract: ISO 8601 UTC with no fractional seconds — Laravel's default adds .000000.
            'created_at' => $this->created_at?->toIso8601ZuluString(),
            'updated_at' => $this->updated_at?->toIso8601ZuluString(),
        ];
    }
}
