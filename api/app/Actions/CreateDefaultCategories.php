<?php

namespace App\Actions;

use App\Models\Category;
use App\Models\User;
use App\Support\DefaultCategories;

class CreateDefaultCategories
{
    public function execute(User $user): void
    {
        foreach (DefaultCategories::ALL as $attributes) {
            // user_id is set by hand, not mass-assigned: it is deliberately not fillable, and
            // nobody is signed in yet at sign-up for the creating stamp to fill it.
            $category = new Category($attributes);
            $category->user_id = $user->id;
            $category->save();
        }
    }
}
