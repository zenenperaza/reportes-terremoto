<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_exposes_the_password_recovery_flow(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee(route('password.request'))
            ->assertSee('&iquest;Olvid&oacute; su contrase&ntilde;a?', false);

        $this->get(route('password.request'))
            ->assertOk()
            ->assertSee('Recuperar contraseña')
            ->assertSee('name="email"', false);
    }

    public function test_registered_user_receives_a_temporary_reset_link(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'persona@example.test']);

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertRedirect()
            ->assertSessionHas('success');

        Notification::assertSentTo($user, ResetPasswordNotification::class, function ($notification): bool {
            $url = route('password.reset', ['token' => $notification->token, 'email' => 'persona@example.test']);

            return str_contains($notification->toMail(User::where('email', 'persona@example.test')->firstOrFail())->actionUrl, $url);
        });
    }

    public function test_unknown_email_receives_the_same_neutral_response(): void
    {
        Notification::fake();

        $this->post(route('password.email'), ['email' => 'desconocido@example.test'])
            ->assertRedirect()
            ->assertSessionHas('success');

        Notification::assertNothingSent();
    }

    public function test_password_can_be_reset_with_a_valid_token(): void
    {
        $user = User::factory()->create(['email' => 'persona@example.test']);
        $token = Password::createToken($user);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'NuevaClave-2026',
            'password_confirmation' => 'NuevaClave-2026',
        ])->assertRedirect(route('login'))->assertSessionHas('success');

        $this->assertTrue(Hash::check('NuevaClave-2026', $user->fresh()->password));
    }

    public function test_invalid_token_does_not_change_the_password(): void
    {
        $user = User::factory()->create(['email' => 'persona@example.test']);
        $originalPassword = $user->password;

        $this->from(route('password.reset', ['token' => 'invalido', 'email' => $user->email]))
            ->post(route('password.update'), [
                'token' => 'invalido',
                'email' => $user->email,
                'password' => 'NuevaClave-2026',
                'password_confirmation' => 'NuevaClave-2026',
            ])->assertRedirect()->assertSessionHasErrors('email');

        $this->assertSame($originalPassword, $user->fresh()->password);
    }
}
