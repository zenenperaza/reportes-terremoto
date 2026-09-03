<?php

namespace Tests\Feature;

use App\Models\EmailTwoFactorCode;
use App\Models\User;
use App\Notifications\EmailTwoFactorCodeNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class TwoFactorAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_without_two_factor_authentication_logs_in_normally(): void
    {
        $user = User::factory()->create(['requires_two_factor' => false, 'is_active' => true]);

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_required_user_must_verify_the_code_sent_by_email(): void
    {
        Notification::fake();
        $user = User::factory()->create(['requires_two_factor' => true, 'is_active' => true]);
        $code = null;

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('two-factor.challenge'));
        $this->assertGuest();

        Notification::assertSentTo($user, EmailTwoFactorCodeNotification::class, function ($notification) use (&$code): bool {
            $code = $notification->code;
            return preg_match('/^\d{6}$/', $code) === 1;
        });

        $this->get(route('two-factor.challenge'))->assertOk()->assertSee('Verifique su acceso');
        $this->post(route('two-factor.verify'), ['code' => $code])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull(EmailTwoFactorCode::first()->consumed_at);
    }

    public function test_invalid_code_does_not_authenticate_the_user(): void
    {
        Notification::fake();
        $user = User::factory()->create(['requires_two_factor' => true, 'is_active' => true]);

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);
        $this->post(route('two-factor.verify'), ['code' => '999999'])
            ->assertSessionHasErrors('code');

        $this->assertGuest();
        $this->assertSame(1, EmailTwoFactorCode::first()->attempts);
    }
}
