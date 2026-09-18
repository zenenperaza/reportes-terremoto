<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\EmailTwoFactorCodeNotification;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiaBrandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_and_authenticated_pages_use_sia(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('Ingresar | SIA')
            ->assertSee('Sistema de Información ASONACOP')->assertDontSee('terremoto')->assertDontSee('Respuesta ASONACOP');
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('reports.index'))->assertOk()
            ->assertSee('Registros | SIA')->assertSee('Sistema de Información ASONACOP')
            ->assertDontSee('terremoto')->assertDontSee('Respuesta Venezuela');
        $this->assertSame('SIA', config('app.name'));
    }

    public function test_installable_app_keeps_its_identity_with_the_new_name(): void
    {
        $manifest = json_decode(file_get_contents(public_path('manifest.webmanifest')), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('SIA', $manifest['short_name']);
        $this->assertSame('SIA — Sistema de Información ASONACOP', $manifest['name']);
        $this->assertSame('/', $manifest['id']);
        $this->assertSame('/panel?source=pwa', $manifest['start_url']);
        $this->assertStringNotContainsString('terremoto', $manifest['description']);
        $this->assertStringContainsString('Sistema de Información ASONACOP', file_get_contents(public_path('offline.html')));
    }

    public function test_security_emails_use_sia_without_sending_mail(): void
    {
        $user = User::factory()->make();
        $mail = (new EmailTwoFactorCodeNotification('123456'))->toMail($user);
        $this->assertSame('Código de acceso - SIA', $mail->subject);
        $this->assertStringContainsString('Sistema de Información ASONACOP', implode(' ', $mail->introLines));
        $reset = (new ResetPasswordNotification('test-token'))->toMail($user);
        $this->assertSame('Recuperación de contraseña | SIA', $reset->subject);
        $this->assertSame('SIA — Sistema de Información ASONACOP', config('mail.from.name'));
    }
}
