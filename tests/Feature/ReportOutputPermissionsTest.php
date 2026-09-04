<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Municipality;
use App\Models\Parish;
use App\Models\Report;
use App\Models\Sector;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ReportOutputPermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_identity_and_output_actions_are_controlled_separately(): void
    {
        $role = Role::findOrCreate('auditor', 'web');
        $role->givePermissionTo('solo ver registros');
        $user = User::factory()->create(['role' => 'auditor', 'countrywide_access' => true]);
        $state = State::create(['code' => 'VE13', 'name' => 'Lara']);
        $municipality = Municipality::create(['state_id' => $state->id, 'code' => 'VE1301', 'name' => 'Iribarren']);
        $parish = Parish::create(['municipality_id' => $municipality->id, 'code' => 'VE130101', 'name' => 'Unión']);
        $sector = Sector::create(['name' => 'Protección', 'slug' => 'proteccion', 'sort_order' => 1]);
        $activity = Activity::create(['sector_id' => $sector->id, 'code' => 'PN-01', 'title' => 'Atención', 'sort_order' => 1]);
        $report = Report::create([
            'user_id' => $user->id, 'report_date' => '2026-09-04', 'reporter_first_name' => 'Usuario',
            'reporter_last_name' => 'Auditor', 'reporter_email' => $user->email, 'organization' => 'ASONACOP',
            'state_id' => $state->id, 'municipality_id' => $municipality->id, 'parish_id' => $parish->id,
            'installation_type' => 'Comunidad / Espacio Comunitario', 'place_name' => 'Comunidad Unión',
            'sector_id' => $sector->id, 'activity_id' => $activity->id, 'recurrence_status' => 'nuevo',
            'total_beneficiaries' => 1, 'beneficiary_breakdown' => [],
        ]);
        $report->beneficiaries()->create([
            'has_informed_consent' => true, 'full_name' => 'NOMBRE CONFIDENCIAL', 'age' => 9, 'sex' => 'Mujer',
            'national_id' => 'V123456', 'phone' => '04140000000', 'disability' => 'Ninguna',
            'ethnicity' => 'Ninguna', 'pregnant_lactating' => 'N/A', 'is_recurrent' => false,
        ]);

        $page = $this->actingAs($user)->get(route('reports.index'));
        $page->assertOk()->assertDontSee('NOMBRE CONFIDENCIAL')
            ->assertDontSee("text: 'Excel'", false)->assertDontSee("text: 'PDF'", false)->assertDontSee('>Ver</a>', false);
        $this->actingAs($user)->get(route('reports.show', $report))->assertForbidden();

        $user->givePermissionTo(['ver detalle de registros', 'exportar registros excel']);
        $page = $this->actingAs($user->fresh())->get(route('reports.index'));
        $page->assertOk()->assertSee("text: 'Excel'", false)->assertDontSee("text: 'PDF'", false)->assertSee('>Ver</a>', false);
        $this->actingAs($user->fresh())->get(route('reports.show', $report))->assertOk();

        $user->givePermissionTo('exportar registros pdf');
        $this->actingAs($user->fresh())->get(route('reports.index'))->assertOk()->assertSee("text: 'PDF'", false);

        $administrator = User::factory()->create(['role' => 'admin']);
        $this->actingAs($administrator)->get(route('reports.index'))
            ->assertOk()->assertDontSee('NOMBRE CONFIDENCIAL')->assertDontSee('V123456')->assertDontSee('04140000000')
            ->assertSee('9 a&ntilde;os', false)->assertSee('Mujer');
    }
}
