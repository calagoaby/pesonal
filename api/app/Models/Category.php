<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'color', 'emoji'])]
class Category extends Model
{
    use BelongsToUser;

    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    /** @return HasMany<Expense, $this> */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    /** @return HasMany<Recurring, $this> */
    public function recurring(): HasMany
    {
        return $this->hasMany(Recurring::class);
    }
}
