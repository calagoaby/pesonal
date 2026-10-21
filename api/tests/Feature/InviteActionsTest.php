<?php

namespace Tests\Feature;

use App\Actions\InviteEmail;
use App\Actions\InviteResult;
use App\Actions\UninviteEmail;
use App\Models\Invitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InviteActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_invite_creates_then_reports_already_invited(): void
    {
        $this->assertSame(InviteResult::Invited, app(InviteEmail::class)->execute(' Friend@Example.com '));
        $this->assertSame(InviteResult::AlreadyInvited, app(InviteEmail::class)->execute('friend@example.com'));
        $this->assertSame(1, Invitation::count());
    }

    public function test_invite_rejects_a_non_email_without_writing(): void
    {
        $this->assertSame(InviteResult::NotAnEmail, app(InviteEmail::class)->execute('not-an-email'));
        $this->assertSame(0, Invitation::count());
    }

    public function test_uninvite_removes_once(): void
    {
        app(InviteEmail::class)->execute('friend@example.com');

        $this->assertTrue(app(UninviteEmail::class)->execute(' Friend@example.com'));
        $this->assertFalse(app(UninviteEmail::class)->execute('friend@example.com'));
    }
}
