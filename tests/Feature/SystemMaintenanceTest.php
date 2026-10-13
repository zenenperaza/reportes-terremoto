<?php

namespace Tests\Feature;

use App\Http\Middleware\BlockDuringSystemMaintenance;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\UserPresence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SystemMaintenanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_enable_and_disable_system_maintenance(): void
    {
        $administrator = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->actingAs($administrator)->get(route('system-maintenance.index'))
            ->assertOk()
            ->assertSee('Mantenimiento del sistema')
            ->assertSee('Sistema disponible')
            ->assertDontSee('id="system-maintenance-banner"', false)
            ->assertSee('sweetalert2@11', false)
            ->assertDontSee('onsubmit="return confirm', false);

        $this->actingAs($administrator)->put(route('system-maintenance.update'), ['enabled' => true])
            ->assertRedirect(route('system-maintenance.index'));
        $this->assertTrue(SystemSetting::maintenanceEnabled());

        foreach (['dashboard', 'reports.index', 'system-maintenance.index'] as $route) {
            $this->actingAs($administrator)->get(route($route))->assertOk()
                ->assertSee('id="system-maintenance-banner"', false)
                ->assertSee('SISTEMA BLOQUEADO')
                ->assertSee('Administrar bloqueo')
                ->assertSee('css/maintenance-banner.css?v=', false)
                ->assertSee('js/maintenance-banner.js?v=', false);
        }

        $this->actingAs($administrator)->put(route('system-maintenance.update'), ['enabled' => false])
            ->assertRedirect(route('system-maintenance.index'));
        $this->assertFalse(SystemSetting::maintenanceEnabled());
        $this->get(route('dashboard'))->assertOk()
            ->assertDontSee('id="system-maintenance-banner"', false)
            ->assertDontSee('has-maintenance-banner', false);
    }

    public function test_maintenance_blocks_reporters_and_coordinators_but_allows_logout(): void
    {
        SystemSetting::create(['key' => SystemSetting::MAINTENANCE_MODE, 'value' => '1']);

        foreach (['reporter', 'coordinator'] as $role) {
            $user = User::factory()->create(['role' => $role, 'is_active' => true]);
            $this->actingAs($user)->get(route('dashboard'))
                ->assertServiceUnavailable()
                ->assertSee(BlockDuringSystemMaintenance::MESSAGE)
                ->assertDontSee('id="system-maintenance-banner"', false);
        }

        $reporter = User::factory()->create(['role' => 'reporter', 'is_active' => true]);
        $this->actingAs($reporter)->post(route('logout'))->assertRedirect(route('login'));
    }

    public function test_maintenance_returns_json_503_for_background_requests(): void
    {
        SystemSetting::create(['key' => SystemSetting::MAINTENANCE_MODE, 'value' => '1']);
        $reporter = User::factory()->create(['role' => 'reporter', 'is_active' => true]);

        $this->actingAs($reporter)->getJson(route('beneficiaries.recurrence'))
            ->assertServiceUnavailable()
            ->assertJson(['message' => BlockDuringSystemMaintenance::MESSAGE]);
    }

    public function test_scheduled_lock_allows_work_until_exact_deadline_without_cron_or_browser(): void
    {
        $this->freezeTime();
        $admin = User::factory()->create(['role' => 'admin']);
        $reporter = User::factory()->create(['role' => 'reporter']);
        $this->actingAs($admin)->put(route('system-maintenance.update'), [
            'enabled' => true, 'delay_minutes' => 5, 'message' => 'Guarde sus registros: actualizaremos SIA.',
        ])->assertRedirect(route('system-maintenance.index'));
        $this->assertFalse(SystemSetting::maintenanceEnabled());
        $this->actingAs($reporter)->get(route('dashboard'))->assertOk()->assertSee('system-live-maintenance.js');
        $this->postJson(route('system-maintenance.status'))->assertOk()->assertJsonPath('scheduled', true)
            ->assertJsonPath('enabled', false)->assertJsonPath('message', 'Guarde sus registros: actualizaremos SIA.')
            ->assertJsonPath('connected_users', 1);
        $this->travel(299)->seconds();
        $this->get(route('dashboard'))->assertOk();
        $this->travel(1)->seconds();
        $this->assertTrue(SystemSetting::maintenanceEnabled());
        $this->get(route('dashboard'))->assertServiceUnavailable();
        $this->putJson(route('profile.update'), [])->assertServiceUnavailable();
        // Even while blocked, polling and logout remain available.
        $this->postJson(route('system-maintenance.status'))->assertOk()->assertJsonPath('enabled', true)->assertJsonPath('scheduled', false);
        $this->actingAs($admin)->get(route('system-maintenance.index'))->assertOk()->assertSee('Sistema bloqueado');
        $this->put(route('system-maintenance.update'), ['enabled' => false])->assertRedirect();
        $this->actingAs($reporter)->get(route('dashboard'))->assertOk();
    }

    public function test_ten_minute_schedule_can_be_cancelled_and_replaced_and_immediate_lock_clears_it(): void
    {
        $this->freezeTime();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->put(route('system-maintenance.update'), ['enabled' => true, 'delay_minutes' => 10])->assertRedirect();
        $this->assertSame(now()->addMinutes(10)->utc()->toIso8601String(), SystemSetting::maintenanceState()['starts_at']);
        $this->travel(2)->minutes();
        $this->put(route('system-maintenance.update'), ['enabled' => true, 'delay_minutes' => 5])->assertRedirect();
        $this->assertSame(now()->addMinutes(5)->utc()->toIso8601String(), SystemSetting::maintenanceState()['starts_at']);
        $this->put(route('system-maintenance.update'), ['enabled' => false])->assertRedirect();
        $this->travel(20)->minutes();
        $this->assertFalse(SystemSetting::maintenanceEnabled());
        $this->assertNull(SystemSetting::maintenanceState()['starts_at']);
        $this->put(route('system-maintenance.update'), ['enabled' => true, 'delay_minutes' => 5])->assertRedirect();
        $this->put(route('system-maintenance.update'), ['enabled' => true])->assertRedirect();
        $this->assertTrue(SystemSetting::maintenanceEnabled());
        $this->assertNull(SystemSetting::maintenanceState()['starts_at']);
        $this->putJson(route('system-maintenance.update'), ['enabled' => true, 'delay_minutes' => 10])->assertUnprocessable();
        $this->assertTrue(SystemSetting::maintenanceEnabled());
    }

    public function test_only_administrators_can_schedule_locks_and_invalid_data_changes_nothing(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'reporter']))
            ->putJson(route('system-maintenance.update'), ['enabled' => true, 'delay_minutes' => 5])->assertForbidden();
        $this->get(route('system-maintenance.index'))->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        foreach ([-1, 1, 6, 11, 'invalid'] as $delay) {
            $this->putJson(route('system-maintenance.update'), ['enabled' => true, 'delay_minutes' => $delay])
                ->assertUnprocessable()->assertJsonValidationErrors('delay_minutes');
        }
        $this->putJson(route('system-maintenance.update'), ['enabled' => true, 'delay_minutes' => 5, 'message' => str_repeat('x', 301)])
            ->assertUnprocessable()->assertJsonValidationErrors('message');
        $this->assertFalse(SystemSetting::maintenanceEnabled());
        $this->assertDatabaseMissing('system_settings', ['key' => SystemSetting::MAINTENANCE_SCHEDULE]);
    }

    public function test_presence_counts_distinct_recent_active_users_and_shares_total_with_authenticated_users(): void
    {
        $this->freezeTime();
        $admin = User::factory()->create(['role' => 'admin']);
        $reporter = User::factory()->create(['role' => 'reporter']);
        $inactive = User::factory()->create(['is_active' => false]);
        $deleted = User::factory()->create();
        $deleted->delete();
        foreach (['tab1' => [$reporter, 0], 'tab2' => [$reporter, 0], 'stale' => [$reporter, 121],
            'inactive' => [$inactive, 0], 'deleted' => [$deleted, 0], 'admin' => [$admin, 0]] as $session => [$user, $age]) {
            DB::table('user_presences')->insert(['session_hash' => hash('sha256', $session), 'user_id' => $user->id, 'last_seen_at' => now()->subSeconds($age)]);
        }
        $this->assertSame(2, UserPresence::connectedCount());
        $this->actingAs($admin)->postJson(route('system-maintenance.status'))->assertOk()->assertJsonPath('connected_users', 2)
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->actingAs($reporter)->postJson(route('system-maintenance.status'))->assertOk()
            ->assertJsonPath('connected_users', 2)->assertJsonPath('administrator', false);
        $coordinator = User::factory()->create(['role' => 'coordinator']);
        $this->actingAs($coordinator)->postJson(route('system-maintenance.status'))->assertOk()
            ->assertJsonPath('connected_users', 3)->assertJsonPath('administrator', false);
        $this->get(route('system-maintenance.index'))->assertForbidden();
        $this->putJson(route('system-maintenance.update'), ['enabled' => true])->assertForbidden();
        $this->travel(121)->seconds();
        $this->assertSame(0, UserPresence::connectedCount());
    }

    public function test_heartbeat_is_session_bound_not_audit_logged_and_logout_removes_only_that_session(): void
    {
        $reporter = User::factory()->create(['role' => 'reporter']);
        DB::table('user_presences')->insert(['session_hash' => hash('sha256', 'other-device'), 'user_id' => $reporter->id, 'last_seen_at' => now()]);
        $auditCount = DB::table('audit_logs')->count();
        $this->withCredentials()->withCookie(config('session.cookie'), str_repeat('a', 40))->actingAs($reporter)
            ->postJson(route('system-maintenance.status'), ['user_id' => 999])->assertOk();
        $this->postJson(route('system-maintenance.status'))->assertOk();
        $this->assertDatabaseCount('user_presences', 2);
        $this->assertSame($auditCount, DB::table('audit_logs')->count());
        $this->assertSame(1, UserPresence::connectedCount());
        $this->post(route('logout'))->assertRedirect();
        $this->assertDatabaseCount('user_presences', 1);
        $this->assertDatabaseHas('user_presences', ['session_hash' => hash('sha256', 'other-device')]);
        $this->postJson(route('system-maintenance.status'))->assertUnauthorized();
    }

    public function test_live_status_remains_available_before_presence_migration_and_inactive_accounts_cannot_poll(): void
    {
        Schema::drop('user_presences');
        $this->actingAs(User::factory()->create(['role' => 'admin']))->postJson(route('system-maintenance.status'))
            ->assertOk()->assertJsonPath('connected_users', null);
        $this->actingAs(User::factory()->create(['role' => 'reporter', 'is_active' => false]))
            ->post(route('system-maintenance.status'))->assertRedirect(route('login'));
    }

    public function test_malformed_schedule_cannot_disable_an_immediate_lock(): void
    {
        SystemSetting::create(['key' => SystemSetting::MAINTENANCE_MODE, 'value' => '1']);
        SystemSetting::create(['key' => SystemSetting::MAINTENANCE_SCHEDULE, 'value' => '{"starts_at":"not a date"}']);
        $this->assertTrue(SystemSetting::maintenanceEnabled());
        $this->actingAs(User::factory()->create(['role' => 'reporter']))->get(route('dashboard'))->assertServiceUnavailable();
    }

    public function test_footer_shows_live_connected_count_to_all_authenticated_roles(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        DB::table('user_presences')->insert(['session_hash' => hash('sha256', 'footer-test'), 'user_id' => $admin->id, 'last_seen_at' => now()]);
        foreach (['dashboard', 'system-maintenance.index'] as $route) {
            $response = $this->actingAs($admin)->get(route($route))->assertOk()
                ->assertSee('id="footer-connected-users"', false)->assertSee('Usuarios conectados:')
                ->assertSee('id="desktop-notifications"', false)->assertSee('js/desktop-notifications.js?v=', false);
            $dom = new \DOMDocument;
            @$dom->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
            $xpath = new \DOMXPath($dom);
            $this->assertSame('1', $xpath->evaluate('string(//footer//*[@data-connected-count])'));
        }
        foreach (['reporter', 'coordinator'] as $role) {
            $response = $this->actingAs(User::factory()->create(['role' => $role]))->get(route('dashboard'))->assertOk()
                ->assertSee('id="footer-connected-users"', false)->assertSee('Usuarios conectados:')
                ->assertSee('js/system-live-maintenance.js?v=', false);
            $response->assertSee('id="desktop-notifications"', false)
                ->assertSee('data-user-id="'.auth()->id().'"', false)->assertSee('js/desktop-notifications.js?v=', false);
            $dom = new \DOMDocument;
            @$dom->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
            $xpath = new \DOMXPath($dom);
            $this->assertSame('1', $xpath->evaluate('string(//footer//*[@data-connected-count])'));
        }
        $this->post(route('logout'))->assertRedirect();
        $this->get(route('login'))->assertOk()->assertDontSee('id="footer-connected-users"', false)
            ->assertDontSee('id="desktop-notifications"', false)->assertDontSee('js/desktop-notifications.js', false);
    }
}
