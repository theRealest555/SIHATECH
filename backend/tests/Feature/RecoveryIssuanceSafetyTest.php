<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AccountCredentials;
use App\Services\ProfileEmail;
use Illuminate\Auth\Events\PasswordResetLinkSent;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class RecoveryIssuanceSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_and_event_follow_the_token_transaction(): void
    {
        Notification::fake();
        Event::fake([PasswordResetLinkSent::class]);
        $user = User::factory()->create();
        $level = DB::transactionLevel();
        $this->postJson('/api/forgot-password', ['email' => $user->email])->assertOk();
        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user, $level) {
            $this->assertSame($level, DB::transactionLevel());
            $this->assertTrue(Password::tokenExists($user, $notification->token));

            return true;
        });
        Event::assertDispatchedTimes(PasswordResetLinkSent::class, 1);
    }

    public function test_token_write_failure_restores_previous_token_and_sends_nothing(): void
    {
        Notification::fake();
        Event::fake([PasswordResetLinkSent::class]);
        $user = User::factory()->create();
        $old = Password::createToken($user);
        $this->travel(61)->seconds();
        DB::listen(function ($query) {
            if (str_starts_with(strtolower($query->sql), 'insert') && str_contains($query->sql, 'password_reset_tokens')) {
                throw new \RuntimeException('Fixture token write failure');
            }
        });
        $this->postJson('/api/forgot-password', ['email' => $user->email])->assertOk();
        $this->assertTrue(Password::tokenExists($user, $old));
        Notification::assertNothingSent();
        Event::assertNotDispatched(PasswordResetLinkSent::class);
    }

    public function test_mail_failure_keeps_committed_token_and_private_response(): void
    {
        $user = User::factory()->create();
        $level = DB::transactionLevel();
        Event::fake([PasswordResetLinkSent::class]);
        Notification::shouldReceive('send')->once()->andReturnUsing(function ($recipient, $notification) use ($level) {
            $this->assertSame($level, DB::transactionLevel());
            $this->assertTrue(Password::tokenExists($recipient, $notification->token));
            throw new \RuntimeException('private transport detail');
        });
        Log::shouldReceive('warning')->once()->with('Password reset delivery failed', ['exception' => \RuntimeException::class]);
        $this->postJson('/api/forgot-password', ['email' => $user->email])->assertOk()->assertDontSee('private transport detail');
        $this->assertDatabaseHas('password_reset_tokens', ['email' => $user->email]);
        Event::assertNotDispatched(PasswordResetLinkSent::class);
    }

    public function test_password_change_during_delivery_invalidates_committed_link(): void
    {
        $user = User::factory()->create();
        Notification::shouldReceive('send')->once()->andReturnUsing(function ($recipient, $notification) {
            app(AccountCredentials::class)->changePassword($recipient->id, 'Changed-during-delivery-2026!');
            $this->assertFalse(Password::tokenExists($recipient, $notification->token));
        });
        $this->postJson('/api/forgot-password', ['email' => $user->email])->assertOk();
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
    }

    public function test_address_reuse_during_delivery_cannot_inherit_the_link(): void
    {
        $user = User::factory()->create();
        $oldAddress = $user->email;
        Notification::shouldReceive('send')->once()->andReturnUsing(function ($recipient, $notification) use ($oldAddress) {
            DB::transaction(function () use ($recipient) {
                $locked = User::whereKey($recipient->id)->lockForUpdate()->firstOrFail();
                app(ProfileEmail::class)->prepare($locked, ['email' => 'new@issuance.test', 'current_password' => 'password']);
                $locked->email = 'new@issuance.test';
                $locked->save();
            });
            $newOwner = User::factory()->create(['email' => $oldAddress]);
            $this->assertFalse(Password::tokenExists($newOwner, $notification->token));
            $this->assertFalse(Password::tokenExists($recipient->fresh(), $notification->token));
        });
        $this->postJson('/api/forgot-password', ['email' => $oldAddress])->assertOk();
        $this->assertSame('new@issuance.test', $user->fresh()->email);
    }
}
