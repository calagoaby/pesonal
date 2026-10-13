<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Every ledger query sees only the signed-in user's rows, so another user's id
 * reads exactly like one that doesn't exist (404).
 *
 * @implements Scope<Model>
 */
class OwnedByCurrentUser implements Scope
{
    /**
     * @param  Builder<covariant Model>  $builder
     */
    public function apply(Builder $builder, Model $model): void
    {
        $userId = auth()->id();

        // Fail closed: a console path that forgets to opt out sees nothing, never
        // everything. The job and claim call withoutGlobalScope() on purpose.
        if ($userId === null) {
            $builder->whereRaw('1 = 0');

            return;
        }

        // Qualified, because the expense list and stats may join.
        $builder->where($model->qualifyColumn('user_id'), $userId);
    }
}
