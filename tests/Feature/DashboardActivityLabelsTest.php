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

class DashboardActivityLabelsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Donante $donor;

    private Sector $sector;

    private array $geography;

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($this->admin);
        $this->donor = Donante::create(['nombre' => 'Donante de prueba', 'estatus' => true]);
        $this->sector = Sector::create(['codigo' => 'CP', 'name' => 'Protección', 'slug' => 'proteccion']);
        $state = State::create(['code' => 'A', 'name' => 'Estado de prueba']);
        $municipality = Municipality::create(['state_id' => $state->id, 'code' => 'A01', 'name' => 'Municipio']);
        $parish = Parish::create(['municipality_id' => $municipality->id, 'code' => 'A0101', 'name' => 'Parroquia']);
        $this->geography = ['state_id' => $state->id, 'municipality_id' => $municipality->id, 'parish_id' => $parish->id];
    }

    private function record(?string $projectAlias, ?string $indicatorName, int $count = 1, ?User $owner = null, bool $excluded = false): Report
    {
        $number = ++$this->sequence;
        $owner ??= $this->admin;
        $project = Proyecto::create(['donante_id' => $this->donor->id, 'codigo' => 'PROY-'.$number,
            'nombre_alias' => $projectAlias, 'descripcion' => 'Descripción completa del proyecto '.$number, 'estatus' => true]);
        $sector = SectorProyecto::create(['proyecto_id' => $project->id, 'sector_id' => $this->sector->id]);
        $indicator = Indicador::create(['codigo' => 'IND-'.$number, 'nombre_corto' => $indicatorName,
            'descripcion' => 'Descripción completa del indicador '.$number, 'unidad_conteo' => 'Personas',
            'espacio_coordinacion' => 'NNA', 'edad_desde' => 0, 'edad_hasta' => 120, 'excluir_reporte_beneficiarios' => $excluded]);
        $assignment = IndicadorProyecto::create(['proyecto_id' => $project->id, 'sector_proyecto_id' => $sector->id,
            'indicador_id' => $indicator->id, 'estatus' => true]);
        $report = Report::create($this->geography + [
            'user_id' => $owner->id, 'proyecto_id' => $project->id, 'indicador_proyecto_id' => $assignment->id,
            'sector_id' => $this->sector->id, 'reporting_period' => '2026-10', 'report_date' => '2026-10-01',
            'reporter_first_name' => 'Prueba', 'reporter_last_name' => 'Panel', 'reporter_email' => $owner->email,
            'organization' => 'ASONACOP', 'installation_type' => 'Comunidad / Espacio Comunitario', 'place_name' => 'Lugar de prueba',
            'recurrence_status' => 'nuevo', 'total_beneficiaries' => $count, 'beneficiary_breakdown' => [],
        ]);
        for ($i = 0; $i < $count; $i++) {
            $report->beneficiaries()->create(['age' => 12, 'sex' => 'Mujer', 'has_informed_consent' => true]);
        }

        return $report;
    }

    public function test_chart_uses_project_alias_and_indicator_short_name_without_changing_counts(): void
    {
        $this->record('  Todos Somos Responsables  ', '  NNA en actividades grupales  ', 3);
        $response = $this->get(route('dashboard'))->assertOk()->assertViewHas('beneficiaryTotal', 3)
            ->assertSee('`${item.project_short_name || item.sector} — ${item.indicator_short_name || item.activity}`', false);
        $items = $response->viewData('activityChart');
        $this->assertCount(1, $items);
        $this->assertSame('Todos Somos Responsables', $items[0]->project_short_name);
        $this->assertSame('NNA en actividades grupales', $items[0]->indicator_short_name);
        $this->assertSame('PROY-1', $items[0]->sector);
        $this->assertSame('Descripción completa del indicador 1', $items[0]->activity);
        $this->assertSame(3, (int) $items[0]->beneficiary_count);
    }

    public function test_missing_blank_short_names_and_legacy_records_keep_the_existing_fallback_labels(): void
    {
        $this->record(null, null);
        $this->record('   ', '   ');
        $legacy = $this->record(null, null);
        $activity = Activity::create(['sector_id' => $this->sector->id, 'code' => 'OLD', 'title' => 'Actividad anterior', 'sort_order' => 1]);
        $legacy->update(['proyecto_id' => null, 'indicador_proyecto_id' => null, 'activity_id' => $activity->id]);
        $items = $this->get(route('dashboard'))->assertOk()->viewData('activityChart')->keyBy('sector');
        foreach (['PROY-1', 'PROY-2'] as $code) {
            $this->assertNull($items[$code]->project_short_name);
            $this->assertNull($items[$code]->indicator_short_name);
            $this->assertStringStartsWith('Descripción completa del indicador ', $items[$code]->activity);
        }
        $this->assertSame('Actividad anterior', $items['Protección']->activity);
        $this->assertNull($items['Protección']->project_short_name);
        $this->assertNull($items['Protección']->indicator_short_name);
        $this->assertSame(3, $items->sum('beneficiary_count'));
    }

    public function test_identical_short_labels_do_not_merge_different_project_indicator_groups(): void
    {
        $this->record('Proyecto corto', 'Indicador corto', 2);
        $this->record('Proyecto corto', 'Indicador corto', 1);
        $items = $this->get(route('dashboard'))->assertOk()->viewData('activityChart');
        $this->assertCount(2, $items);
        $this->assertSame([2, 1], $items->pluck('beneficiary_count')->map(fn ($value) => (int) $value)->all());
        $this->assertSame(['PROY-1', 'PROY-2'], $items->pluck('sector')->all());
    }

    public function test_label_changes_preserve_user_visibility_and_excluded_indicator_rules(): void
    {
        $reporter = User::factory()->create(['role' => 'reporter']);
        $other = User::factory()->create(['role' => 'reporter']);
        $this->record('Mi proyecto', 'Mi indicador', 2, $reporter);
        $this->record('Proyecto de otro usuario', 'Indicador de otro usuario', 3, $other);
        $this->record('Proyecto excluido', 'Indicador excluido', 4, $reporter, true);
        $response = $this->actingAs($reporter)->get(route('dashboard'))->assertOk()
            ->assertViewHas('beneficiaryTotal', 2)->assertViewHas('beneficiaryGrandTotal', 6);
        $this->assertCount(1, $response->viewData('activityChart'));
        $this->assertSame('Mi proyecto', $response->viewData('activityChart')[0]->project_short_name);
        $this->assertSame('Mi indicador', $response->viewData('activityChart')[0]->indicator_short_name);
        $response = $this->actingAs($this->admin)->get(route('dashboard'))->assertOk()->assertViewHas('beneficiaryTotal', 5);
        $this->assertCount(2, $response->viewData('activityChart'));
        $this->assertSame(5, $response->viewData('activityChart')->sum('beneficiary_count'));
    }
}
