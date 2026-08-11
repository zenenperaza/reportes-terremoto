<?php

namespace Tests\Feature;

use App\Http\Middleware\BlockDuringSystemMaintenance;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            ->assertSee('sweetalert2@11', false)
            ->assertDontSee('onsubmit="return confirm', false);

        $this->actingAs($administrator)->put(route('system-maintenance.update'), ['enabled' => true])
            ->assertRedirect(route('system-maintenance.index'));
        $this->assertTrue(SystemSetting::maintenanceEnabled());

        $this->actingAs($administrator)->get(route('dashboard'))->assertOk();

        $this->actingAs($administrator)->put(route('system-maintenance.update'), ['enabled' => false])
            ->assertRedirect(route('system-maintenance.index'));
        $this->assertFalse(SystemSetting::maintenanceEnabled());
    }

    public function test_maintenance_blocks_reporters_and_coordinators_but_allows_logout(): void
    {
        SystemSetting::create(['key' => SystemSetting::MAINTENANCE_MODE, 'value' => '1']);

        foreach (['reporter', 'coordinator'] as $role) {
            $user = User::factory()->create(['role' => $role, 'is_active' => true]);
            $this->actingAs($user)->get(route('dashboard'))
                ->assertServiceUnavailable()
                ->assertSee(BlockDuringSystemMaintenance::MESSAGE);
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
}
