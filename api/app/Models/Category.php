<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'color', 'emoji'])]
class Category extends Model
{
    use HasFactory;

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function recurring(): HasMany
    {
        return $this->hasMany(Recurring::class);
    }
}