<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmailVerificationRenderingTest extends TestCase
{
    use RefreshDatabase;

    public function test_verification_email_renders_and_reaches_the_mail_transport(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);
        Sanctum::actingAs($user);

        $this->postJson('/api/email/verification-notification')
            ->assertOk()->assertJson(['status' => 'verification-link-sent']);

        $messages = Mail::mailer('array')->getSymfonyTransport()->messages();
        $this->assertCount(1, $messages);
        $message = $messages->first()->getOriginalMessage();
        $this->assertSame($user->email, $message->getTo()[0]->getAddress());
        $this->assertStringContainsString('/api/email/verify/'.$user->id.'/'.sha1($user->email), $message->getHtmlBody());
        $this->assertStringContainsString('signature=', $message->getTextBody());
    }
}
