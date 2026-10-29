<?php

namespace Tests\Feature;

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
use App\Models\SummaryIndicator;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class BeneficiaryProjectFilterTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private array $projects = [];
    private array $reports = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin', 'can_mark_reported' => true]);
        $this->actingAs($this->admin);
        $state = State::create(['code' => 'TEST', 'name' => 'Estado']);
        $municipality = Municipality::create(['state_id' => $state->id, 'code' => 'TEST01', 'name' => 'Municipio']);
        $parish = Parish::create(['municipality_id' => $municipality->id, 'code' => 'TEST0101', 'name' => 'Parroquia']);
        $donor = Donante::create(['nombre' => 'Donante', 'estatus' => true]);
        foreach ([1, 2, 3] as $number) {
            $this->projects[$number] = Proyecto::create([
                'donante_id' => $donor->id, 'codigo' => 'PROY-'.$number,
                'nombre_alias' => 'Proyecto '.$number, 'descripcion' => 'Descripción '.$number, 'estatus' => $number !== 3,
            ]);
        }
        foreach ([1, 2, 3, 0] as $number) {
            $report = Report::create([
                'proyecto_id' => $this->projects[$number]->id ?? null, 'user_id' => $this->admin->id,
                'report_date' => '2026-10-'.(10 + $number), 'reporting_period' => '2026-10',
                'state_id' => $state->id, 'municipality_id' => $municipality->id, 'parish_id' => $parish->id,
                'reporter_first_name' => 'Prueba', 'reporter_last_name' => 'Proyecto',
                'reporter_email' => $this->admin->email, 'organization' => 'ASONACOP',
                'installation_type' => 'Comunidad / Espacio Comunitario', 'place_name' => 'Lugar '.$number,
                'recurrence_status' => 'nuevo', 'total_beneficiaries' => 1, 'beneficiary_breakdown' => [],
            ]);
            $beneficiary = $report->beneficiaries()->create([
                'age' => 12, 'sex' => 'Mujer', 'is_recurrent' => $number === 2,
            ]);
            $beneficiary->forceFill(['created_at' => '2026-10-'.(15 + $number).' 12:00:00'])->save();
            $this->reports[$number] = $report;
        }
    }

    public function test_default_includes_all_projects_and_renders_full_width_multiselect_without_preselection(): void
    {
        $response = $this->get(route('beneficiaries.summary'))->assertOk()
            ->assertViewHas('filters', fn ($filters) => $filters['project_ids'] === [])
            ->assertViewHas('summary', fn ($summary) => $summary['total'] === 4)
            ->assertViewHas('projectOptions', fn ($options) => $options->modelKeys() === array_values(array_map(fn ($project) => $project->id, $this->projects)))
            ->assertSee('beneficiary-project-filter.js');
        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument;
        $dom->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new \DOMXPath($dom);
        $select = $xpath->query('//form[@id="beneficiary-project-scope"]/label[contains(@class,"beneficiary-project-filter")]/select[@name="project_ids[]"][@multiple]');
        $this->assertCount(1, $select);
        $this->assertSame('Todos los proyectos', $select->item(0)->getAttribute('data-placeholder'));
        $this->assertSame('Seleccionar todos los proyectos', $xpath->query('./option[1]', $select->item(0))->item(0)->textContent);
        $this->assertCount(0, $xpath->query('./option[@selected]', $select->item(0)));
        $response->assertSeeInOrder(['id="beneficiary-project-scope"', 'id="summary_reported"', 'id="reporting-period"', 'name="from"'], false);
    }

    public function test_one_or_multiple_projects_filter_every_result_and_keep_selection(): void
    {
        foreach ([[1], [1, 2], [1, 2, 3], []] as $numbers) {
            $ids = array_map(fn ($number) => $this->projects[$number]->id, $numbers);
            $expected = $numbers === [] ? 4 : count($numbers);
            $response = $this->get(route('beneficiaries.summary', ['project_ids' => $ids]))->assertOk()
                ->assertViewHas('filters', fn ($filters) => $filters['project_ids'] === $ids)
                ->assertViewHas('summary', fn ($summary) => $summary['total'] === $expected)
                ->assertViewHas('summary345w', fn ($summary) => $summary['total'] === $expected)
                ->assertViewHas('reportCount', $expected)
                ->assertViewHas('pendingBeneficiaryCount', $expected)
                ->assertViewHas('groupedBeneficiaries', fn ($groups) => $groups->sum('beneficiary_count') === $expected);
            foreach ($ids as $id) $response->assertSee('value="'.$id.'" selected', false);
        }
    }

    public function test_project_selection_intersects_period_status_and_recurrence_filters(): void
    {
        $this->reports[1]->beneficiaries()->update(['reported_at' => '2026-10-20', 'reported' => true]);
        foreach ([['1', '', 1], ['0', '', 1], ['', '1', 1], ['', '0', 1]] as [$reported, $recurrent, $expected]) {
            $this->get(route('beneficiaries.summary', [
                'project_ids' => [$this->projects[1]->id, $this->projects[2]->id],
                'reported' => $reported, 'is_recurrent' => $recurrent, 'reporting_period' => ['2026-10'],
            ]))->assertOk()->assertViewHas('summary', fn ($summary) => $summary['total'] === $expected);
        }
        $this->get(route('beneficiaries.summary', ['project_ids' => [$this->projects[1]->id], 'reported' => '', 'reporting_period' => ['2026-09']]))
            ->assertOk()->assertViewHas('summary', fn ($summary) => $summary['total'] === 0);
    }

    public function test_date_bounds_follow_selected_projects_and_out_of_scope_dates_are_rejected(): void
    {
        $filters = ['project_ids' => [$this->projects[1]->id, $this->projects[2]->id], 'reported' => ''];
        $this->getJson(route('beneficiaries.dates', $filters))->assertOk()->assertExactJson([
            'attention' => ['min' => '2026-10-11', 'max' => '2026-10-12'],
            'registered' => ['min' => '2026-10-16', 'max' => '2026-10-17'],
        ]);
        foreach (['beneficiaries.summary', 'beneficiaries.export'] as $route) {
            $this->getJson(route($route, $filters + ['from' => '2026-10-10']))->assertUnprocessable()->assertJsonValidationErrors('from');
            $this->get(route($route, $filters + ['from' => '2026-10-12']))->assertOk();
        }
    }

    public function test_excel_contains_only_selected_projects_and_removing_one_excludes_it(): void
    {
        foreach ([[1, 2], [2], [3], []] as $numbers) {
            $filters = ['project_ids' => array_map(fn ($number) => $this->projects[$number]->id, $numbers)];
            $response = $this->get(route('beneficiaries.export', $filters))->assertOk()->assertDownload();
            $path = tempnam(sys_get_temp_dir(), 'beneficiary-project-export-');
            try {
                file_put_contents($path, $response->streamedContent());
                $spreadsheet = IOFactory::load($path);
                $sheet = $spreadsheet->getActiveSheet();
                $actual = [];
                for ($row = 2; $row <= $sheet->getHighestDataRow(); $row++) {
                    if ($sheet->getCell('A'.$row)->getValue() !== null) $actual[] = (string) $sheet->getCell('I'.$row)->getValue();
                }
                $expected = $numbers === [] ? ['PROY-1', 'PROY-2', 'PROY-3', ''] : array_map(fn ($number) => 'PROY-'.$number, $numbers);
                sort($actual);
                sort($expected);
                $this->assertSame($expected, $actual);
                $spreadsheet->disconnectWorksheets();
            } finally {
                @unlink($path);
            }
        }
    }

    public function test_project_options_and_manual_requests_never_bypass_report_visibility(): void
    {
        $reporter = User::factory()->create(['role' => 'reporter']);
        $reporter->projects()->attach($this->projects[2]);
        $this->reports[1]->update(['user_id' => $reporter->id]);
        $this->actingAs($reporter)->get(route('beneficiaries.summary'))->assertOk()
            ->assertViewHas('projectOptions', fn ($options) => $options->modelKeys() === [$this->projects[1]->id, $this->projects[2]->id])
            ->assertViewHas('summary', fn ($summary) => $summary['total'] === 1);
        $this->get(route('beneficiaries.summary', ['project_ids' => [$this->projects[2]->id]]))->assertOk()
            ->assertViewHas('summary', fn ($summary) => $summary['total'] === 0);
        $this->getJson(route('beneficiaries.summary', ['project_ids' => [$this->projects[3]->id]]))->assertUnprocessable()
            ->assertJsonValidationErrors('project_ids.0');
        $this->getJson(route('beneficiaries.dates', ['project_ids' => [$this->projects[2]->id]]))->assertOk()->assertExactJson([
            'attention' => ['min' => null, 'max' => null], 'registered' => ['min' => null, 'max' => null],
        ]);
    }

    public function test_invalid_array_ids_duplicates_and_select_all_sentinel_are_rejected(): void
    {
        foreach (['bad', ['__all__'], [99999], [$this->projects[1]->id, $this->projects[1]->id], [['nested']]] as $value) {
            foreach (['beneficiaries.summary', 'beneficiaries.export', 'beneficiaries.dates'] as $route) {
                $this->getJson(route($route, ['project_ids' => $value]))->assertUnprocessable();
            }
        }
        $this->get(route('beneficiaries.summary', ['project_ids' => '']))->assertOk()
            ->assertViewHas('filters', fn ($filters) => $filters['project_ids'] === []);
    }

    public function test_mark_reported_only_updates_beneficiaries_from_selected_projects(): void
    {
        $this->post(route('beneficiaries.mark-reported'), [
            'project_ids' => [$this->projects[2]->id], 'reported_at' => '2026-10-01',
        ])->assertRedirect()->assertSessionHasNoErrors();
        foreach ($this->reports as $number => $report) {
            $this->assertSame($number === 2, $report->beneficiaries()->first()->reported_at !== null);
        }
    }

    private function addDistinctProjectOptions(): array
    {
        $options = [];
        foreach ([1, 2, 3] as $number) {
            $project = $this->projects[$number];
            $state = State::create(['code' => 'S'.$number, 'name' => 'Estado '.$number]);
            $municipality = Municipality::create(['state_id' => $state->id, 'code' => 'M'.$number, 'name' => 'Municipio '.$number]);
            $parish = Parish::create(['municipality_id' => $municipality->id, 'code' => 'P'.$number, 'name' => 'Parroquia '.$number]);
            $sector = Sector::create(['name' => 'Sector '.$number, 'slug' => 'sector-'.$number]);
            $sectorAssignment = SectorProyecto::create(['proyecto_id' => $project->id, 'sector_id' => $sector->id]);
            $indicator = Indicador::create(['codigo' => 'IND-'.$number, 'descripcion' => 'Indicador '.$number,
                'unidad_conteo' => 'Personas', 'espacio_coordinacion' => 'NNA', 'edad_desde' => 0, 'edad_hasta' => 120]);
            $assignment = IndicadorProyecto::create(['proyecto_id' => $project->id, 'sector_proyecto_id' => $sectorAssignment->id,
                'indicador_id' => $indicator->id, 'estatus' => true]);
            $summary = SummaryIndicator::create(['proyecto_id' => $project->id, 'code' => 'SUM-'.$number, 'name' => 'Resumen '.$number, 'is_active' => true]);
            $summary->assignments()->attach($assignment);
            $this->reports[$number]->update([
                'state_id' => $state->id, 'municipality_id' => $municipality->id, 'parish_id' => $parish->id,
                'sector_id' => $sector->id, 'indicador_proyecto_id' => $assignment->id,
                'reporting_period' => [1 => '2026-09', 2 => '2026-10', 3 => null][$number],
                'installation_type' => config('reports.installation_types')[$number],
            ]);
            $options[$number] = compact('state', 'municipality', 'parish', 'sector', 'indicator', 'assignment', 'summary');
        }
        return $options;
    }

    public function test_every_filter_option_is_scoped_to_one_or_multiple_projects(): void
    {
        $options = $this->addDistinctProjectOptions();
        foreach ([[1], [1, 2], [3]] as $numbers) {
            $ids = array_map(fn ($number) => $this->projects[$number]->id, $numbers);
            $response = $this->get(route('beneficiaries.summary', ['project_ids' => $ids]))->assertOk();
            foreach (['states' => 'state', 'municipalities' => 'municipality', 'parishes' => 'parish',
                'sectors' => 'sector', 'summaryIndicatorOptions' => 'summary'] as $field => $key) {
                $expected = array_map(fn ($number) => $options[$number][$key]->id, $numbers);
                $response->assertViewHas($field, fn ($items) => $items->pluck('id')->sort()->values()->all() === $expected);
            }
            $response->assertViewHas('indicatorOptions', fn ($items) => $items->pluck('code')->sort()->values()->all() === array_map(fn ($number) => 'IND-'.$number, $numbers))
                ->assertViewHas('places', fn ($items) => $items->all() === array_map(fn ($number) => 'Lugar '.$number, $numbers))
                ->assertViewHas('installationTypes', fn ($items) => $items->count() === count($numbers))
                ->assertViewHas('periodOptions', fn ($items) => $items->keys()->sort()->values()->all() === array_values(array_filter(array_map(fn ($number) => [1 => '2026-09', 2 => '2026-10', 3 => null][$number], $numbers))))
                ->assertViewHas('includeUnassignedPeriod', in_array(3, $numbers, true))
                ->assertViewHas('recurrenceOptions', fn ($items) => $items === (in_array(2, $numbers, true) ? (count($numbers) === 1 ? ['1'] : ['0', '1']) : ['0']));
            if ($numbers === [1]) $response->assertDontSee('value="unassigned"', false);
        }
        $this->get(route('beneficiaries.summary'))->assertOk()
            ->assertViewHas('summaryIndicatorOptions', fn ($items) => $items->count() === 3)
            ->assertViewHas('indicatorOptions', fn ($items) => $items->count() === 3)
            ->assertViewHas('states', fn ($items) => $items->count() === 4);
    }

    public function test_scoped_options_keep_reporting_status_permissions_and_indicator_exclusions(): void
    {
        $options = $this->addDistinctProjectOptions();
        $this->reports[2]->beneficiaries()->update(['reported_at' => '2026-10-20', 'reported' => true]);
        $ids = [$this->projects[1]->id, $this->projects[2]->id];
        $this->get(route('beneficiaries.summary', ['project_ids' => $ids, 'reported' => '0']))->assertOk()
            ->assertViewHas('indicatorOptions', fn ($items) => $items->pluck('code')->all() === ['IND-1'])
            ->assertViewHas('periodOptions', fn ($items) => $items->keys()->all() === ['2026-09']);
        $this->get(route('beneficiaries.summary', ['project_ids' => $ids, 'reported' => '1']))->assertOk()
            ->assertViewHas('indicatorOptions', fn ($items) => $items->pluck('code')->all() === ['IND-2'])
            ->assertViewHas('periodOptions', fn ($items) => $items->keys()->all() === ['2026-10']);
        $options[1]['indicator']->update(['excluir_reporte_beneficiarios' => true]);
        $this->get(route('beneficiaries.summary', ['project_ids' => [$this->projects[1]->id]]))->assertOk()
            ->assertViewHas('indicatorOptions', fn ($items) => $items->isEmpty())
            ->assertViewHas('periodOptions', fn ($items) => $items->isEmpty())
            ->assertViewHas('states', fn ($items) => $items->isEmpty());
        $reporter = User::factory()->create(['role' => 'reporter']);
        $reporter->projects()->attach($this->projects[2]);
        $this->actingAs($reporter)->get(route('beneficiaries.summary', ['project_ids' => [$this->projects[2]->id]]))->assertOk()
            ->assertViewHas('indicatorOptions', fn ($items) => $items->isEmpty())
            ->assertViewHas('periodOptions', fn ($items) => $items->isEmpty())
            ->assertViewHas('summaryIndicatorOptions', fn ($items) => $items->isEmpty());
    }

    public function test_location_requests_cannot_reintroduce_another_project_and_forms_preserve_scope(): void
    {
        $options = $this->addDistinctProjectOptions();
        $ids = [$this->projects[1]->id];
        $this->getJson(route('beneficiaries.locations', ['project_ids' => $ids]))->assertOk()->assertExactJson([
            'states' => [['id' => $options[1]['state']->id, 'name' => 'Estado 1']],
            'municipalities' => [['id' => $options[1]['municipality']->id, 'name' => 'Municipio 1']],
            'parishes' => [['id' => $options[1]['parish']->id, 'name' => 'Parroquia 1']],
        ]);
        $this->getJson(route('beneficiaries.locations', ['project_ids' => $ids, 'state_id' => $options[2]['state']->id]))->assertOk()
            ->assertJsonCount(0, 'municipalities')->assertJsonCount(0, 'parishes');
        $response = $this->get(route('beneficiaries.summary', ['project_ids' => $ids]))->assertOk();
        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument;
        $dom->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new \DOMXPath($dom);
        foreach (['beneficiary-reported-filter', 'beneficiary-report-filters'] as $id) {
            $hidden = $xpath->query('//form[@id="'.$id.'"]//input[@type="hidden"][@name="project_ids[]"]');
            $this->assertCount(1, $hidden);
            $this->assertSame((string) $ids[0], $hidden->item(0)->getAttribute('value'));
        }
        $scope = $xpath->query('//form[@id="beneficiary-project-scope"]')->item(0);
        $this->assertCount(0, $xpath->query('.//input[not(@name="reported")]', $scope));
    }
}
