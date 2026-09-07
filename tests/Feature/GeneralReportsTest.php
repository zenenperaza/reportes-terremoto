<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Donante;
use App\Models\Indicador;
use App\Models\IndicadorProyecto;
use App\Models\Municipality;
use App\Models\Parish;
use App\Models\Proyecto;
use App\Models\Report;
use App\Models\Sector;
use App\Models\SectorProyecto;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GeneralReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_general_report_displays_and_filters_chart_data(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $state = State::create(['code' => 'VE13', 'name' => 'Lara']);
        $municipality = Municipality::create(['state_id' => $state->id, 'code' => 'VE1301', 'name' => 'Iribarren']);
        $parish = Parish::create(['municipality_id' => $municipality->id, 'code' => 'VE130101', 'name' => 'Unión']);
        $sector = Sector::create(['codigo' => 'PN', 'descripcion' => 'Protección', 'estatus' => true, 'name' => 'Protección', 'slug' => 'proteccion', 'sort_order' => 1]);
        $activity = Activity::create(['sector_id' => $sector->id, 'code' => 'PN-01', 'title' => 'Atención de protección', 'sort_order' => 1, 'active' => true]);
        $donor = Donante::create(['nombre' => 'UNICEF', 'estatus' => true]);
        $project = Proyecto::create(['donante_id' => $donor->id, 'estatus' => true, 'codigo' => 'PR-1', 'descripcion' => 'Proyecto']);
        $projectSector = SectorProyecto::create(['proyecto_id' => $project->id, 'sector_id' => $sector->id]);
        $indicator = Indicador::create(['codigo' => 'PN/01', 'descripcion' => 'Personas atendidas', 'unidad_conteo' => 'Personas', 'espacio_coordinacion' => 'NNA', 'edad_desde' => 0, 'edad_hasta' => 120]);
        $indicatorAssignment = IndicadorProyecto::create(['proyecto_id' => $project->id, 'sector_proyecto_id' => $projectSector->id, 'indicador_id' => $indicator->id, 'estatus' => true]);
        $report = Report::create([
            'user_id' => $user->id,
            'proyecto_id' => $project->id,
            'indicador_proyecto_id' => $indicatorAssignment->id,
            'report_date' => '2026-08-04',
            'reporter_first_name' => 'Ana',
            'reporter_last_name' => 'Pérez',
            'reporter_email' => 'ana@example.test',
            'organization' => 'ASONACOP',
            'state_id' => $state->id,
            'municipality_id' => $municipality->id,
            'parish_id' => $parish->id,
            'installation_type' => 'Comunidad / Espacio Comunitario',
            'place_name' => 'Comunidad Unión',
            'sector_id' => $sector->id,
            'activity_id' => $activity->id,
            'recurrence_status' => 'nuevo',
            'total_beneficiaries' => 2,
            'beneficiary_breakdown' => [],
        ]);
        $report->beneficiaries()->createMany([
            ['has_informed_consent' => true, 'full_name' => 'JUAN', 'age' => 10, 'sex' => 'Hombre', 'disability' => 'Ninguna', 'ethnicity' => 'Ninguna', 'pregnant_lactating' => 'N/A'],
            ['has_informed_consent' => true, 'full_name' => 'MARÍA', 'age' => 35, 'sex' => 'Mujer', 'disability' => 'Ninguna', 'ethnicity' => 'Ninguna', 'pregnant_lactating' => 'No'],
        ]);

        $this->actingAs($user)->get(route('general-reports.index'))
            ->assertOk()
            ->assertSee('Informes Generales')
            ->assertSee('Personas atendidas')
            ->assertSee('general-age-chart', false)
            ->assertSee('general-sex-chart', false)
            ->assertSee('PN/01 - Personas atendidas')
            ->assertDontSee('Otra actividad del sector');

        $this->actingAs($user)->get(route('general-reports.index', ['indicador_id' => $indicator->id]))
            ->assertOk()
            ->assertSee('value="'.$indicator->id.'" selected', false);

        $this->actingAs($user)->get(route('general-reports.index', ['age_from' => 18, 'sex' => 'Mujer']))
            ->assertOk()
            ->assertSee('value="18"', false)
            ->assertSee('value="Mujer" selected', false)
            ->assertSee('1', false);

        $this->actingAs($user)->get(route('general-reports.index', ['age_from' => 18, 'age_to' => 59, 'age_group' => '0-5']))
            ->assertOk()
            ->assertSee('id="general_age_from"', false)
            ->assertDontSee('name="age_from" min="0" max="120" value="18"', false)
            ->assertSee('value="0-5" selected', false)
            ->assertSee('Use el rango de edad o el grupo etario, no ambos.');
    }
}
