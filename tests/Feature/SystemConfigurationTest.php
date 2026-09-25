<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_configuration_with_current_date_without_saving_on_get(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 25));
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->actingAs($admin)->get(route('system-configuration.index'))->assertOk()
            ->assertViewHas('period', ['month' => 9, 'year' => 2026])
            ->assertViewHas('months', fn ($months) => count($months) === 12 && $months[1] === 'Enero' && $months[12] === 'Diciembre')
            ->assertViewHas('years', range(2021, 2031))
            ->assertSee('value="9" selected', false)->assertSee('value="2026" selected', false)
            ->assertSee('href="'.route('system-configuration.index').'"', false)
            ->assertSee('Guardar configuraciones');
        $this->assertDatabaseMissing('system_settings', ['key' => SystemSetting::CURRENT_PERIOD]);
    }

    public function test_period_is_saved_together_and_preserved_without_changing_other_settings(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 25));
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        SystemSetting::create(['key' => SystemSetting::MAINTENANCE_MODE, 'value' => '1']);
        SystemSetting::create(['key' => SystemSetting::AUTOMATIC_BACKUP_LAST_AT, 'value' => '2026-09-25 00:00:00']);
        $this->actingAs($admin)->put(route('system-configuration.update'), [
            'period_month' => '12', 'period_year' => '2031', 'maintenance_mode' => '0',
        ])->assertRedirect(route('system-configuration.index'))->assertSessionHas('success');
        $this->assertDatabaseHas('system_settings', ['key' => SystemSetting::CURRENT_PERIOD, 'value' => '2031-12', 'updated_by' => $admin->id]);
        $this->assertDatabaseHas('system_settings', ['key' => SystemSetting::MAINTENANCE_MODE, 'value' => '1']);
        $this->assertSame(['month' => 12, 'year' => 2031], SystemSetting::currentPeriod());
        $this->get(route('system-configuration.index'))->assertOk()
            ->assertSee('value="12" selected', false)->assertSee('value="2031" selected', false);
        $this->put(route('system-configuration.update'), ['period_month' => 1, 'period_year' => 2021])->assertRedirect();
        $this->assertSame(['month' => 1, 'year' => 2021], SystemSetting::currentPeriod());
        $this->assertSame(1, SystemSetting::where('key', SystemSetting::CURRENT_PERIOD)->count());
        $this->assertDatabaseCount('reports', 0);
        $this->assertDatabaseCount('beneficiaries', 0);
    }

    public function test_invalid_months_years_and_incomplete_periods_do_not_replace_saved_value(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 25));
        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));
        SystemSetting::create(['key' => SystemSetting::CURRENT_PERIOD, 'value' => '2026-09']);
        foreach ([
            [[], ['period_month', 'period_year']],
            [['period_month' => 0, 'period_year' => 2026], ['period_month']],
            [['period_month' => 13, 'period_year' => 2026], ['period_month']],
            [['period_month' => [1], 'period_year' => 2026], ['period_month']],
            [['period_month' => 1, 'period_year' => 2020], ['period_year']],
            [['period_month' => 1, 'period_year' => 2032], ['period_year']],
            [['period_month' => 1, 'period_year' => 'incorrecto'], ['period_year']],
        ] as [$data, $errors]) {
            $this->putJson(route('system-configuration.update'), $data)->assertUnprocessable()->assertJsonValidationErrors($errors);
            $this->assertDatabaseHas('system_settings', ['key' => SystemSetting::CURRENT_PERIOD, 'value' => '2026-09']);
        }
    }

    public function test_guests_and_non_admins_cannot_view_or_change_configuration(): void
    {
        $this->get(route('system-configuration.index'))->assertRedirect(route('login'));
        $this->put(route('system-configuration.update'), ['period_month' => 1, 'period_year' => 2026])->assertRedirect(route('login'));
        foreach (['reporter', 'coordinator'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role, 'is_active' => true]));
            $this->get(route('system-configuration.index'))->assertForbidden();
            $this->put(route('system-configuration.update'), ['period_month' => 1, 'period_year' => 2026])->assertForbidden();
            $this->get(route('dashboard'))->assertOk()->assertDontSee('href="'.route('system-configuration.index').'"', false);
        }
        $this->assertDatabaseMissing('system_settings', ['key' => SystemSetting::CURRENT_PERIOD]);
    }

    public function test_new_calendar_year_does_not_change_saved_period_and_extends_year_options(): void
    {
        SystemSetting::create(['key' => SystemSetting::CURRENT_PERIOD, 'value' => '2026-09']);
        $this->travelTo(now()->setDate(2027, 1, 1));
        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]))
            ->get(route('system-configuration.index'))->assertOk()
            ->assertViewHas('period', ['month' => 9, 'year' => 2026])
            ->assertViewHas('years', range(2021, 2032));
    }
}
