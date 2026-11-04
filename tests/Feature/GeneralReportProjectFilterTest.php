<?php

namespace Tests\Feature;

use App\Models\{Donante, Indicador, IndicadorProyecto, Municipality, Parish, Proyecto, Report, Sector, SectorProyecto, State, SummaryIndicator, SystemSetting, User, UserGroup};
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class GeneralReportProjectFilterTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private array $projects = [], $reports = [], $indicators = [], $summaries = [];

    protected function setUp(): void
    {
        parent::setUp();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->owner = User::factory()->create(['role' => 'reporter']);
        $outsider = User::factory()->create(['role' => 'reporter']);
        $this->actingAs($admin);
        SystemSetting::updateOrCreate(['key' => SystemSetting::CURRENT_PERIOD], ['value' => '2026-11']);
        $donor = Donante::create(['nombre' => 'Donante', 'estatus' => true]);
        foreach ([1, 2, 3, 0] as $n) {
            $state = State::create(['code' => 'P'.$n, 'name' => 'Estado '.$n]);
            $municipality = Municipality::create(['state_id' => $state->id, 'code' => 'M'.$n, 'name' => 'Municipio '.$n]);
            $parish = Parish::create(['municipality_id' => $municipality->id, 'code' => 'PA'.$n, 'name' => 'Parroquia '.$n]);
            $sector = Sector::create(['name' => 'Sector '.$n, 'slug' => 'project-sector-'.$n]);
            $assignment = null;
            if ($n) {
                $project = $this->projects[$n] = Proyecto::create(['donante_id' => $donor->id, 'codigo' => 'PROY-'.$n,
                    'nombre_alias' => 'Proyecto '.$n, 'descripcion' => 'Descripción', 'estatus' => $n !== 2]);
                $sectorAssignment = SectorProyecto::create(['proyecto_id' => $project->id, 'sector_id' => $sector->id]);
                $indicator = $this->indicators[$n] = Indicador::create(['codigo' => 'IND-'.$n, 'descripcion' => 'Indicador '.$n,
                    'unidad_conteo' => 'Personas', 'espacio_coordinacion' => 'NNA', 'edad_desde' => 0, 'edad_hasta' => 120,
                    'excluir_reporte_beneficiarios' => $n === 2]);
                $assignment = IndicadorProyecto::create(['proyecto_id' => $project->id, 'sector_proyecto_id' => $sectorAssignment->id,
                    'indicador_id' => $indicator->id, 'estatus' => true]);
                $summary = $this->summaries[$n] = SummaryIndicator::create(['proyecto_id' => $project->id,
                    'code' => 'RES-'.$n, 'name' => 'Resumen '.$n, 'is_active' => true]);
                $summary->assignments()->attach($assignment);
            }
            $owner = $n === 3 ? $outsider : $this->owner;
            $report = $this->reports[$n] = Report::create(['user_id' => $owner->id, 'proyecto_id' => $this->projects[$n]->id ?? null,
                'indicador_proyecto_id' => $assignment?->id, 'sector_id' => $sector->id,
                'report_date' => '2026-10-'.(10 + $n), 'reporting_period' => $n === 2 ? '2026-09' : '2026-10',
                'reporter_first_name' => 'Prueba', 'reporter_last_name' => '', 'reporter_email' => $owner->email,
                'organization' => 'ASONACOP', 'state_id' => $state->id, 'municipality_id' => $municipality->id, 'parish_id' => $parish->id,
                'place_name' => 'Lugar '.$n, 'installation_type' => config('reports.installation_types')[$n % 2],
                'recurrence_status' => 'no_recurrente', 'total_beneficiaries' => 1, 'beneficiary_breakdown' => []]);
            $report->beneficiaries()->create(['age' => $n === 1 ? 12 : 30, 'sex' => $n === 1 ? 'Mujer' : 'Hombre',
                'disability' => $n === 1 ? 'Discapacidad Visual' : 'Ninguna', 'pregnant_lactating' => $n === 1 ? 'Sí' : 'No', 'is_recurrent' => $n === 2])
                ->forceFill(['reported_at' => $n === 2 ? '2026-10-25' : null, 'created_at' => '2026-10-'.(20 + $n).' 12:00:00'])->save();
        }
    }

    private function ids(array $numbers): array {return array_map(fn ($n) => $this->projects[$n]->id, $numbers);}

    public function test_default_all_projects_and_project_scope_form_is_first_multiselect(): void
    {
        $response = $this->get(route('general-reports.index'))->assertOk()
            ->assertViewHas('filters', fn ($f) => $f['project_ids'] === [])
            ->assertViewHas('summary', fn ($s) => $s['beneficiaries'] === 4)
            ->assertSeeInOrder(['id="general-project-scope"', 'id="general-reported-filter"', 'id="general-report-filters"'], false)
            ->assertSee('data-placeholder="Todos los proyectos"', false)->assertSee('general-project-filter.js');
        preg_match('/<select id="general_project_ids".*?<\/select>/s', $response->getContent(), $select);
        $this->assertStringContainsString('multiple', $select[0]);
        $this->assertStringContainsString('Seleccionar todos los proyectos', $select[0]);
        $this->assertStringNotContainsString(' selected', $select[0]);
    }

    public function test_single_or_multiple_projects_filter_cards_all_charts_and_keep_beneficiary_excluded_indicators(): void
    {
        foreach ([[1], [2], [1, 2]] as $numbers) {
            $this->get(route('general-reports.index', ['project_ids' => $this->ids($numbers)]))->assertOk()
                ->assertViewHas('summary', fn ($s) => $s['beneficiaries'] === count($numbers) && $s['disability'] === (int) in_array(1, $numbers) && $s['pregnancy'] === (int) in_array(1, $numbers))
                ->assertViewHas('charts', function ($charts) use ($numbers) {
                    return array_sum($charts['sex']['values']) === count($numbers)
                        && collect($charts['ages']['men'])->sum() + collect($charts['ages']['women'])->sum() === count($numbers)
                        && $charts['states']['labels']->sort()->values()->all() === array_map(fn ($n) => 'Estado '.$n, $numbers)
                        && $charts['trend']['labels']->count() === count($numbers);
                });
        }
    }

    public function test_every_filter_option_and_date_bounds_are_limited_to_the_selected_projects(): void
    {
        $this->get(route('general-reports.index', ['project_ids' => $this->ids([1])]))->assertOk()
            ->assertViewHas('periodOptions', fn ($options) => $options->keys()->all() === ['2026-10'])
            ->assertViewHas('includeUnassignedPeriod', false)
            ->assertViewHas('summaryIndicatorOptions', fn ($options) => $options->modelKeys() === [$this->summaries[1]->id])
            ->assertViewHas('indicators', fn ($options) => $options->pluck('id')->all() === [$this->indicators[1]->id])
            ->assertViewHas('states', fn ($options) => $options->modelKeys() === [$this->reports[1]->state_id])
            ->assertViewHas('municipalities', fn ($options) => $options->pluck('id')->all() === [$this->reports[1]->municipality_id])
            ->assertViewHas('parishes', fn ($options) => $options->pluck('id')->all() === [$this->reports[1]->parish_id])
            ->assertViewHas('places', fn ($options) => $options->all() === ['Lugar 1'])
            ->assertViewHas('sectors', fn ($options) => $options->modelKeys() === [$this->reports[1]->sector_id])
            ->assertViewHas('sexOptions', fn ($options) => $options->all() === ['Mujer'])
            ->assertViewHas('recurrenceOptions', ['0'])
            ->assertViewHas('dateBounds', fn ($bounds) => $bounds['attention']['min'] === '2026-10-11' && $bounds['attention']['max'] === '2026-10-11'
                && $bounds['registered']['min'] === '2026-10-21' && $bounds['registered']['max'] === '2026-10-21');
    }

    public function test_reported_period_and_summary_filters_intersect_projects_and_forms_retain_the_scope(): void
    {
        $filters = ['project_ids' => $this->ids([1, 2]), 'reported' => '1', 'reporting_period' => ['2026-09'],
            'summary_indicator_ids' => [$this->summaries[2]->id]];
        $this->get(route('general-reports.index', $filters))->assertOk()->assertViewHas('summary', fn ($s) => $s['beneficiaries'] === 1);
        $this->get(route('general-reports.index', array_replace($filters, ['project_ids' => $this->ids([1])])))
            ->assertOk()->assertViewHas('summary', fn ($s) => $s['beneficiaries'] === 0);
        $response = $this->get(route('general-reports.index', ['project_ids' => $this->ids([1]), 'reported' => '1']))->assertOk()
            ->assertViewHas('periodOptions', fn ($options) => $options->isEmpty())
            ->assertViewHas('summary', fn ($s) => $s['beneficiaries'] === 0);
        $this->assertSame(2, substr_count($response->getContent(), '<input type="hidden" name="project_ids[]" value="'.$this->projects[1]->id.'">'));
    }

    public function test_locations_and_date_endpoints_keep_project_scope_and_reject_unavailable_projects(): void
    {
        $filters = ['project_ids' => $this->ids([2])];
        $this->getJson(route('general-reports.locations', $filters))->assertOk()->assertJsonCount(1, 'states')
            ->assertJsonPath('states.0.id', $this->reports[2]->state_id)->assertJsonPath('municipalities.0.id', $this->reports[2]->municipality_id);
        $this->getJson(route('general-reports.dates', $filters))->assertOk()->assertJsonPath('attention.min', '2026-10-12')->assertJsonPath('registered.max', '2026-10-22');
        foreach (['1', ['__all__'], [999999], [1, 1], [['invalid']]] as $ids) {
            foreach (['general-reports.index', 'general-reports.export', 'general-reports.dates', 'general-reports.locations'] as $route) {
                $this->getJson(route($route, ['project_ids' => $ids]))->assertUnprocessable();
            }
        }
    }

    public function test_project_selection_never_widens_team_visibility_and_historical_inactive_projects_remain_available(): void
    {
        $this->actingAs($this->owner)->get(route('general-reports.index'))->assertOk()
            ->assertViewHas('projectOptions', fn ($options) => $options->modelKeys() === $this->ids([1, 2]));
        $this->getJson(route('general-reports.index', ['project_ids' => $this->ids([3])]))->assertUnprocessable();
        $this->owner->projects()->attach($this->projects[3]);
        $this->get(route('general-reports.index', ['project_ids' => $this->ids([3])]))->assertOk()->assertViewHas('summary', fn ($s) => $s['beneficiaries'] === 0);
        $coordinator = User::factory()->create(['role' => 'coordinator']);
        $group = UserGroup::create(['name' => 'Equipo', 'is_active' => true]);
        $group->users()->attach([$coordinator->id, $this->owner->id]);
        $this->actingAs($coordinator)->get(route('general-reports.index', ['project_ids' => $this->ids([2])]))->assertOk()
            ->assertViewHas('summary', fn ($s) => $s['beneficiaries'] === 1);
    }

    public function test_excel_totals_charts_and_applied_project_names_follow_the_selection(): void
    {
        foreach ([[1], [1, 2]] as $numbers) {
            $response = $this->get(route('general-reports.export', ['project_ids' => $this->ids($numbers)]))->assertOk();
            $path = tempnam(sys_get_temp_dir(), 'general-project-export-');
            try {
                file_put_contents($path, $response->streamedContent());
                $workbook = IOFactory::load($path);
                $sheet = $workbook->getSheetByName('Resumen');
                $this->assertSame(count($numbers), $sheet->getCell('B3')->getValue());
                $rows = $sheet->toArray();
                $projectRows = array_values(array_filter($rows, fn ($row) => $row[0] === 'Proyectos'));
                $this->assertCount(1, $projectRows);
                foreach ($numbers as $n) $this->assertStringContainsString('PROY-'.$n, $projectRows[0][1]);
                $this->assertStringNotContainsString('PROY-3', $projectRows[0][1]);
                $details = json_encode($workbook->getSheetByName('Desgloses')->toArray());
                foreach ($numbers as $n) $this->assertStringContainsString('Estado '.$n, $details);
                $this->assertStringNotContainsString('Estado 3', $details);
                $this->assertStringNotContainsString('Estado 0', $details);
                $workbook->disconnectWorksheets();
            } finally {@unlink($path);}
        }
    }

    public function test_unselected_projects_include_legacy_records_and_indicator_report_has_no_new_scope_control(): void
    {
        $this->get(route('general-reports.index', ['project_ids' => '']))->assertOk()->assertViewHas('summary', fn ($s) => $s['beneficiaries'] === 4);
        $this->get(route('indicator-reports.index'))->assertOk()->assertDontSee('id="general-project-scope"', false);
    }

    public function test_project_sector_options_and_filter_work_when_only_the_indicator_assignment_has_the_sector(): void
    {
        $sectorId = $this->reports[1]->sector_id;
        $this->reports[1]->update(['sector_id' => null]);
        $this->get(route('general-reports.index', ['project_ids' => $this->ids([1]), 'sector_id' => $sectorId]))->assertOk()
            ->assertViewHas('summary', fn ($s) => $s['beneficiaries'] === 1)
            ->assertViewHas('sectors', fn ($options) => $options->modelKeys() === [$sectorId]);
    }
}
