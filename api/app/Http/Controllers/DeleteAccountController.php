<?php

namespace App\Http\Controllers;

use App\Models\Invitation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;

class DeleteAccountController extends Controller
{
    public function __invoke(Request $request): Response
    {
        /** @var User $user auth:sanctum guarantees one */
        $user = $request->user();

        // Delete first: if the transaction throws, the user stays signed in with their
        // data intact instead of signed out with it still there.
        DB::transaction(function () use ($user) {
            // The user_id foreign keys cascade, but expenses and standing orders also hold
            // a RESTRICT key to their category, and the order a database runs cascades in
            // isn't promised. Deleting the ledger children-first never trips it. The raw
            // table queries take voided rows too and skip the ownership scope.
            foreach (['expenses', 'recurring', 'categories'] as $table) {
                DB::table($table)->where('user_id', $user->id)->delete();
            }
            // API tokens hang off a polymorphic key, which no foreign key can cascade.
            $user->tokens()->delete();
            // Reset tokens are keyed by email; left behind, one would outlive the account.
            Password::broker()->deleteToken($user);
            // Signed-in sessions (IP and browser) go too. Only applies under the database session driver, as in SignInWithProvider.
            DB::table(config('session.table'))->where('user_id', $user->id)->delete();
            // A returning friend needs a fresh pesonal:invite.
            Invitation::query()->where('email', Invitation::normalize($user->email))->delete();
            // Social accounts cascade.
            $user->delete();
        });

        // Not logout(): it cycles the remember token and save()s the user, which on a
        // deleted model inserts the row straight back. With the row gone, no remember
        // cookie can resolve anyway.
        Auth::guard('web')->logoutCurrentDevice();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->noContent();
    }
}
