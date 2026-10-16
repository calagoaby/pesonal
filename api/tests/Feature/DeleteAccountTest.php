<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Expense;
use App\Models\Recurring;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class DeleteAccountTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{category: Category, expense: Expense, recurring: Recurring} */
    private function ledgerFor(User $user): array
    {
        $category = Category::factory()->create(['user_id' => $user->id]);
        $recurring = Recurring::factory()->create(['user_id' => $user->id, 'category_id' => $category->id]);
        $expense = Expense::factory()->create(['user_id' => $user->id, 'category_id' => $category->id, 'recurring_id' => $recurring->id]);

        return compact('category', 'expense', 'recurring');
    }

    public function test_delete_account_removes_the_user_and_all_their_rows(): void
    {
        $a = User::factory()->create(['email' => 'a@example.com', 'password' => 'correct horse battery']);
        $b = User::factory()->create();
        $this->ledgerFor($a);
        Expense::factory()->create(['user_id' => $a->id, 'category_id' => Category::factory()->create(['user_id' => $a->id])->id, 'deleted_at' => now()]);
        $this->ledgerFor($b);

        $this->fromFrontend()
            ->postJson('/api/auth/login', ['email' => 'a@example.com', 'password' => 'correct horse battery'])
            ->assertOk();

        $this->fromFrontend()->deleteJson('/api/user')->assertNoContent();

        $this->assertDatabaseMissing('users', ['id' => $a->id]);
        foreach (['categories', 'recurring', 'expenses'] as $table) {
            $this->assertSame(0, DB::table($table)->where('user_id', $a->id)->count(), $table);
        }
        $this->assertSame(1, DB::table('categories')->where('user_id', $b->id)->count());
        $this->assertSame(1, DB::table('recurring')->where('user_id', $b->id)->count());
        $this->assertSame(1, DB::table('expenses')->where('user_id', $b->id)->count());

        // A fresh request reads the (now invalidated) session, like the browser's next call would.
        $this->app['auth']->forgetGuards();

        $this->fromFrontend()->getJson('/api/user')->assertUnauthorized();
    }

    public function test_delete_account_removes_a_pending_password_reset_token(): void
    {
        $user = User::factory()->create(['email' => 'a@example.com']);
        Password::createToken($user);
        $this->assertDatabaseHas('password_reset_tokens', ['email' => 'a@example.com']);

        $this->fromFrontend()->actingAs($user)->deleteJson('/api/user')->assertNoContent();

        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'a@example.com']);
    }

    public function test_a_remember_cookie_stops_working_once_the_account_is_deleted(): void
    {
        User::factory()->create(['email' => 'a@example.com', 'password' => 'correct horse battery']);
        $recaller = Auth::guard('web')->getRecallerName();
        $cookie = (string) $this->fromFrontend()
            ->postJson('/api/auth/login', ['email' => 'a@example.com', 'password' => 'correct horse battery', 'remember' => true])
            ->assertOk()
            ->getCookie($recaller)?->getValue();

        $this->fromFrontend()->deleteJson('/api/user')->assertNoContent();

        $this->app['auth']->forgetGuards();
        $this->app['session.store']->flush();

        $this->fromFrontend()->withCredentials()->withCookie($recaller, $cookie)->getJson('/api/user')->assertUnauthorized();
    }

    public function test_delete_account_needs_a_signed_in_user(): void
    {
        $this->deleteJson('/api/user')->assertUnauthorized();
    }
}
