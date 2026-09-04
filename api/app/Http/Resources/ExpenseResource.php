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
            'amount' => $this->amount, // centavos, bare int
            'date' => $this->date->format('Y-m-d'), // calendar date, no time
            'note' => $this->note,
            'category' => CategoryRefResource::make($this->category), // 4-field ref, never category_id
            'recurring_id' => $this->recurring_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
