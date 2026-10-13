<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class DesktopActionNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private function events($response): array
    {
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        $xpath = new \DOMXPath($dom);

        return json_decode($xpath->evaluate('string(//*[@id="desktop-notification-events"])'), true, flags: JSON_THROW_ON_ERROR);
    }

    public function test_success_messages_become_generic_notifications_for_all_authenticated_roles(): void
    {
        foreach (['admin', 'coordinator', 'reporter'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            foreach (['saved' => 'Beneficiario guardado: MARIA V12345678',
                'updated' => 'Usuario actualizado correctamente.', 'deleted' => 'Registro eliminado correctamente.',
                'success' => 'El registro fue marcado como revisado.'] as $kind => $message) {
                $events = $this->events($this->withSession(['success' => $message])->get(route('dashboard'))->assertOk());
                $this->assertCount(1, $events);
                $this->assertSame($kind, $events[0]['kind']);
                $this->assertMatchesRegularExpression('/^[a-f0-9-]{36}$/', $events[0]['id']);
                $this->assertStringNotContainsString('MARIA', json_encode($events));
                $this->assertStringNotContainsString('V12345678', json_encode($events));
            }
        }
    }

    public function test_validation_and_operation_errors_emit_only_a_generic_event(): void
    {
        $this->actingAs(User::factory()->create());
        $errors = (new ViewErrorBag)->put('default', new MessageBag(['name' => 'MARIA tiene datos inválidos.']));
        $events = $this->events($this->withSession(['error' => 'No se pudo eliminar MARIA.', 'errors' => $errors])
            ->get(route('dashboard'))->assertOk());
        $this->assertCount(1, $events);
        $this->assertSame('error', $events[0]['kind']);
        $this->assertStringNotContainsString('MARIA', json_encode($events));
    }

    public function test_normal_navigation_does_not_emit_action_events_and_guests_have_no_desktop_controls(): void
    {
        $this->actingAs(User::factory()->create());
        $response = $this->get(route('dashboard'))->assertOk()
            ->assertSee('data-desktop-test-result role="status" aria-live="polite" aria-atomic="true" hidden', false);
        $this->assertSame([], $this->events($response));
        $this->post(route('logout'))->assertRedirect();
        $this->get(route('login'))->assertOk()->assertDontSee('id="desktop-notification-events"', false);
    }

    public function test_login_welcome_event_is_sent_only_after_authentication_and_expires_after_rendering(): void
    {
        $user = User::factory()->create(['requires_two_factor' => false]);
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('dashboard'))->assertSessionHas('desktop_login_event');
        $events = $this->events($this->get(route('dashboard'))->assertOk());
        $this->assertCount(1, $events);
        $this->assertSame('opened', $events[0]['kind']);
        $this->assertSame([], $this->events($this->get(route('dashboard'))->assertOk()));
    }
}
