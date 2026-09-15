<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_user_actions_are_recorded_and_credentials_are_hidden(): void
    {
        $user = User::factory()->create([
            'role' => 'reporter',
            'is_active' => true,
            'email' => 'registro@example.test',
            'password' => 'password-seguro',
        ]);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password-seguro',
        ])->assertRedirect(route('dashboard'));

        $loginLog = AuditLog::query()->where('route_name', 'login.store')->firstOrFail();

        $this->assertSame($user->id, $loginLog->user_id);
        $this->assertSame('Inició sesión', $loginLog->action);
        $this->assertSame('[OCULTO]', $loginLog->request_payload['password']);
        $this->assertSame($user->email, $loginLog->request_payload['email']);

        $this->get(route('dashboard'))->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'route_name' => 'dashboard',
            'method' => 'GET',
            'status_code' => 200,
        ]);
    }

    public function test_only_administrators_can_access_the_audit_log(): void
    {
        $reporter = User::factory()->create(['role' => 'reporter', 'is_active' => true]);
        $administrator = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->actingAs($reporter)->get(route('audit-logs.index'))->assertForbidden();
        $this->actingAs($reporter)->getJson(route('audit-logs.data'))->assertForbidden();
        $this->actingAs($administrator)->get(route('audit-logs.index'))
            ->assertOk()
            ->assertSee('Bitácora del sistema')
            ->assertSee('serverSide: true', false);
    }

    public function test_server_side_endpoint_filters_and_paginates_actions(): void
    {
        $administrator = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $otherUser = User::factory()->create(['role' => 'reporter', 'is_active' => true]);

        AuditLog::query()->create([
            'user_id' => $otherUser->id,
            'user_name' => $otherUser->name,
            'user_email' => $otherUser->email,
            'action' => 'Actualizó un registro de prueba',
            'method' => 'PUT',
            'route_name' => 'reports.update',
            'path' => '/reportes/15',
            'status_code' => 302,
            'duration_ms' => 24,
            'ip_address' => '127.0.0.1',
        ]);

        $response = $this->actingAs($administrator)->getJson(route('audit-logs.data', [
            'draw' => 7,
            'start' => 0,
            'length' => 25,
            'user_id' => $otherUser->id,
            'method' => 'PUT',
            'search' => ['value' => 'prueba'],
            'order' => [['column' => 0, 'dir' => 'desc']],
        ]));

        $response->assertOk()
            ->assertJsonPath('draw', 7)
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.user_name', $otherUser->name)
            ->assertJsonPath('data.0.method', 'PUT')
            ->assertJsonPath('data.0.path', '/reportes/15');
    }
}
