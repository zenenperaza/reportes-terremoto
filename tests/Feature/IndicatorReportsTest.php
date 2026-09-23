<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Donante;
use App\Models\Indicador;
use App\Models\IndicadorProyecto;
use App\Models\IndicatorGroup;
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

class IndicatorReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_indicator_report_displays_filters_cards_and_grouped_indicator_totals(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $state = State::create(['code' => 'VE13', 'name' => 'Lara']);
        $municipality = Municipality::create(['state_id' => $state->id, 'code' => 'VE1301', 'name' => 'Iribarren']);
        $parish = Parish::create(['municipality_id' => $municipality->id, 'code' => 'VE130101', 'name' => 'Uni?n']);
        $sector = Sector::create(['codigo' => 'PN', 'descripcion' => 'Protecci?n', 'estatus' => true, 'name' => 'Protecci?n', 'slug' => 'proteccion', 'sort_order' => 1]);
        $activity = Activity::create(['sector_id' => $sector->id, 'code' => 'PN-01', 'title' => 'Atenci?n de protecci?n', 'sort_order' => 1, 'active' => true]);
        $donor = Donante::create(['nombre' => 'UNICEF', 'estatus' => true]);
        $project = Proyecto::create(['donante_id' => $donor->id, 'estatus' => true, 'codigo' => 'PR-1', 'descripcion' => 'Proyecto']);
        $projectSector = SectorProyecto::create(['proyecto_id' => $project->id, 'sector_id' => $sector->id]);
        $group = IndicatorGroup::create(['name' => 'Apoyo psicosocial', 'description' => 'Grupo de protecci?n', 'sort_order' => 1]);
        $indicator = Indicador::create(['indicator_group_id' => $group->id, 'codigo' => 'PN/01', 'nombre_corto' => 'Personas atendidas cortas', 'descripcion' => 'Personas atendidas', 'unidad_conteo' => 'Personas', 'espacio_coordinacion' => 'NNA', 'edad_desde' => 0, 'edad_hasta' => 120]);
        $indicatorAssignment = IndicadorProyecto::create(['proyecto_id' => $project->id, 'sector_proyecto_id' => $projectSector->id, 'indicador_id' => $indicator->id, 'estatus' => true]);
        $secondIndicator = Indicador::create(['indicator_group_id' => $group->id, 'codigo' => 'PN/02', 'nombre_corto' => 'Otra poblaci?n atendida corta', 'descripcion' => 'Otra poblaci?n atendida', 'unidad_conteo' => 'Personas', 'espacio_coordinacion' => 'NNA', 'edad_desde' => 0, 'edad_hasta' => 120]);
        $secondIndicatorAssignment = IndicadorProyecto::create(['proyecto_id' => $project->id, 'sector_proyecto_id' => $projectSector->id, 'indicador_id' => $secondIndicator->id, 'estatus' => true]);
        $report = Report::create([
            'user_id' => $user->id,
            'proyecto_id' => $project->id,
            'indicador_proyecto_id' => $indicatorAssignment->id,
            'report_date' => '2026-08-04',
            'reporter_first_name' => 'Ana',
            'reporter_last_name' => 'P?rez',
            'reporter_email' => 'ana@example.test',
            'organization' => 'ASONACOP',
            'state_id' => $state->id,
            'municipality_id' => $municipality->id,
            'parish_id' => $parish->id,
            'installation_type' => 'Comunidad / Espacio Comunitario',
            'place_name' => 'Comunidad Uni?n',
            'sector_id' => $sector->id,
            'activity_id' => $activity->id,
            'recurrence_status' => 'nuevo',
            'total_beneficiaries' => 2,
            'beneficiary_breakdown' => [],
        ]);
        $report->beneficiaries()->createMany([
            ['has_informed_consent' => true, 'full_name' => 'JUAN', 'age' => 10, 'sex' => 'Hombre', 'disability' => 'Ninguna', 'ethnicity' => 'Ninguna', 'pregnant_lactating' => 'N/A'],
            ['has_informed_consent' => true, 'full_name' => 'MAR?A', 'age' => 35, 'sex' => 'Mujer', 'disability' => 'Ninguna', 'ethnicity' => 'Ninguna', 'pregnant_lactating' => 'No'],
        ]);
        $secondReport = $report->replicate();
        $secondReport->fill([
            'indicador_proyecto_id' => $secondIndicatorAssignment->id,
            'total_beneficiaries' => 1,
        ]);
        $secondReport->save();
        $secondReport->beneficiaries()->create([
            'has_informed_consent' => true,
            'full_name' => 'LUIS',
            'age' => 24,
            'sex' => 'Hombre',
            'disability' => 'Ninguna',
            'ethnicity' => 'Ninguna',
            'pregnant_lactating' => 'No',
        ]);

        $this->actingAs($user)->get(route('indicator-reports.index', ['indicador_id' => [$indicator->id, $secondIndicator->id]]))
            ->assertOk()
            ->assertSee('Informe por Indicadores')
            ->assertSee('Filtros del informe')
            ->assertSee('Personas atendidas')
            ->assertSee('Hombres adultos')
            ->assertSee('NNA Hombres')
            ->assertSee('Mujeres adultas')
            ->assertSee('NNA Mujeres')
            ->assertDontSee('Registros de atenci')
            ->assertSee('Beneficiarios por indicadores')
            ->assertSee('Atención Apoyo psicosocial (2)')
            ->assertDontSee('class="indicator-group-total"', false)
            ->assertSee('Mujeres: 1')
            ->assertSee('Hombres: 2')
            ->assertSee('Total: 3')
            ->assertSee('Personas atendidas cortas')
            ->assertSee('Otra poblaci?n atendida corta')
            ->assertSee('PN/01')
            ->assertSee('PN/02')
            ->assertSee('Edad: 0 a 120 a')
            ->assertSee('Total beneficiarios: 3.')
            ->assertSee('H: 1')
            ->assertSee('M: 1')
            ->assertSee('name="indicador_id[]"', false)
            ->assertSee('value="'.$indicator->id.'" selected', false)
            ->assertSee('value="'.$secondIndicator->id.'" selected', false)
            ->assertSee('<strong>3</strong>', false)
            ->assertDontSee('general-age-chart', false)
            ->assertDontSee('general-sex-chart', false)
            ->assertDontSee('assets/libs/apexcharts/apexcharts.min.js', false);
    }
}
