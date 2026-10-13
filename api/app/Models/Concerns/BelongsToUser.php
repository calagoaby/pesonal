<?php

namespace App\Models\Concerns;

use App\Models\Scopes\OwnedByCurrentUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToUser
{
    public static function bootBelongsToUser(): void
    {
        static::addGlobalScope(new OwnedByCurrentUser);

        // Stamp only when nobody set it and someone is signed in. The job and the seeders
        // pass user_id themselves; a forgotten one is left empty to fail on the column.
        static::creating(function (Model $model) {
            if (empty($model->getAttribute('user_id')) && auth()->id() !== null) {
                $model->setAttribute('user_id', auth()->id());
            }
        });
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
