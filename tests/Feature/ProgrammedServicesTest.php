<?php

namespace Tests\Feature;

use App\Models\Actividad;
use App\Models\ActividadIndicador;
use App\Models\Donante;
use App\Models\Indicador;
use App\Models\IndicadorProyecto;
use App\Models\Municipality;
use App\Models\Parish;
use App\Models\Proyecto;
use App\Models\Report;
use App\Models\Sector;
use App\Models\SectorProyecto;
use App\Models\Servicio;
use App\Models\ServicioActividad;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProgrammedServicesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private array $items = [];

    private array $geography;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($this->admin);
        $state = State::create(['code' => 'A', 'name' => 'Estado']);
        $municipality = Municipality::create(['state_id' => $state->id, 'code' => 'A01', 'name' => 'Municipio']);
        $parish = Parish::create(['municipality_id' => $municipality->id, 'code' => 'A0101', 'name' => 'Parroquia']);
        $this->geography = ['state_id' => $state->id, 'municipality_id' => $municipality->id, 'parish_id' => $parish->id];
        $donor = Donante::create(['nombre' => 'Donante', 'estatus' => true]);
        $sharedService = Servicio::create(['nombre' => 'Kit compartido', 'descripcion' => 'Un mismo servicio en dos proyectos']);
        foreach (['A', 'B'] as $code) {
            $project = Proyecto::create(['donante_id' => $donor->id, 'codigo' => 'PROY-'.$code, 'descripcion' => 'Proyecto '.$code, 'estatus' => true]);
            $sector = Sector::create(['name' => 'Sector '.$code, 'slug' => 'sector-'.strtolower($code), 'estatus' => true]);
            $projectSector = SectorProyecto::create(['proyecto_id' => $project->id, 'sector_id' => $sector->id]);
            $indicator = Indicador::create(['codigo' => 'IND-'.$code, 'descripcion' => 'Indicador '.$code, 'unidad_conteo' => 'Personas', 'espacio_coordinacion' => 'NNA', 'edad_desde' => 0, 'edad_hasta' => 120]);
            $assignedIndicator = IndicadorProyecto::create(['proyecto_id' => $project->id, 'sector_proyecto_id' => $projectSector->id, 'indicador_id' => $indicator->id, 'estatus' => true]);
            $activity = Actividad::create(['codigo' => 'ACT-'.$code, 'descripcion' => 'Actividad '.$code]);
            $assignedActivity = ActividadIndicador::create(['indicador_proyecto_id' => $assignedIndicator->id, 'actividad_id' => $activity->id, 'estatus' => true]);
            $assignment = ServicioActividad::create(['actividad_indicador_id' => $assignedActivity->id, 'servicio_id' => $sharedService->id,
                'estatus' => $code === 'A', 'cantidad_disponible' => $code === 'A' ? 10 : 0]);
            $this->items[$code] = compact('project', 'sector', 'indicator', 'assignedIndicator', 'activity', 'assignedActivity', 'assignment');
        }
        $unused = Servicio::create(['nombre' => 'Orientación sin cantidad']);
        $this->items['C'] = $this->items['A'];
        $this->items['C']['assignment'] = ServicioActividad::create(['actividad_indicador_id' => $this->items['A']['assignedActivity']->id,
            'servicio_id' => $unused->id, 'estatus' => true, 'cantidad_disponible' => null]);
        Servicio::create(['nombre' => 'Servicio de catálogo no programado']);
        $this->record($this->items['A']['assignment'], $this->admin, 2);
        $this->record($this->items['A']['assignment'], $this->admin, 1);
        $this->record($this->items['B']['assignment'], $this->admin, 2);
    }

    private function record(ServicioActividad $assignment, User $owner, int $people): Report
    {
        $activity = $assignment->actividadIndicador;
        $report = Report::create($this->geography + [
            'user_id' => $owner->id, 'proyecto_id' => $activity->indicadorProyecto->proyecto_id,
            'indicador_proyecto_id' => $activity->indicador_proyecto_id, 'actividad_indicador_id' => $activity->id,
            'reporting_period' => '2026-09', 'report_date' => '2026-09-15', 'reporter_first_name' => 'Prueba',
            'reporter_last_name' => 'Servicios', 'reporter_email' => $owner->email, 'organization' => 'ASONACOP',
            'installation_type' => 'Comunidad / Espacio Comunitario', 'place_name' => 'Lugar',
            'recurrence_status' => 'nuevo', 'total_beneficiaries' => $people, 'beneficiary_breakdown' => [],
        ]);
        $report->serviciosActividad()->attach($assignment);
        for ($i = 0; $i < $people; $i++) {
            $report->beneficiaries()->create(['full_name' => 'PERSONA CONFIDENCIAL', 'age' => 12, 'sex' => 'Mujer']);
        }

        return $report;
    }

    public function test_overview_shows_each_assignment_counts_reports_not_people_and_preserves_configuration(): void
    {
        $response = $this->get(route('servicios-programados.index'))->assertOk()
            ->assertSee('Informes por Servicios')->assertSee('Kit compartido')->assertSee('PROY-A')->assertSee('PROY-B')
            ->assertSee('IND-A')->assertSee('ACT-B')->assertSee('Servicios entregados')
            ->assertSee('no son personas únicas ni unidades entregadas')->assertSee('Registros, no beneficiarios')
            ->assertDontSee('Cantidad disponible configurada')->assertDontSee('Orientación sin cantidad')
            ->assertDontSee('PERSONA CONFIDENCIAL')->assertDontSee('Servicio de catálogo no programado')
            ->assertSee('id="service-report-table"', false)->assertSee('js/service-report-table.js', false)
            ->assertSee('responsive.dataTables.min.css')->assertSee('dataTables.responsive.min.js')
            ->assertSee('button-excel-export')->assertSee('programmed-service-table-wrap')
            ->assertSee('Exportar Excel')
            ->assertViewHas('asignaciones', fn ($items) => $items->count() === 2);
        $items = $response->viewData('asignaciones')->keyBy('id');
        $this->assertSame(2, $items[$this->items['A']['assignment']->id]->reports_count);
        $this->assertSame(1, $items[$this->items['B']['assignment']->id]->reports_count);
        $this->assertSame(3, (int) $items[$this->items['A']['assignment']->id]->beneficiaries_count);
        $this->assertSame(2, (int) $items[$this->items['B']['assignment']->id]->beneficiaries_count);
        $this->assertSame(10, $this->items['A']['assignment']->fresh()->cantidad_disponible);
        $this->assertSame(0, $this->items['B']['assignment']->fresh()->cantidad_disponible);
        $this->assertNull($this->items['C']['assignment']->fresh()->cantidad_disponible);
        $this->assertDatabaseCount('report_servicio_actividad', 3);
        foreach ([$this->items['A'], $this->items['B']] as $item) {
            $response->assertSee(route('actividad-indicador.servicios.index', $item['assignedActivity']), false);
            $this->assertStringContainsString(e(route('reports.index', ['servicio_actividad_id' => $item['assignment']->id, 'reported' => '', 'reporting_period' => '', 'from' => '', 'to' => ''])), $response->getContent());
        }
    }

    public function test_all_filters_apply_together_and_inactive_assignments_are_included_by_default(): void
    {
        $a = $this->items['A'];
        foreach (['proyecto_id' => 'project', 'sector_id' => 'sector', 'indicador_id' => 'indicator', 'actividad_id' => 'activity'] as $field => $key) {
            $this->get(route('servicios-programados.index', [$field => $a[$key]->id]))->assertOk()
                ->assertViewHas('asignaciones', fn ($items) => $items->pluck('id')->all() === [$a['assignment']->id]);
        }
        $params = ['proyecto_id' => $a['project']->id, 'sector_id' => $a['sector']->id, 'indicador_id' => $a['indicator']->id,
            'actividad_id' => $a['activity']->id, 'servicio_id' => $a['assignment']->servicio_id, 'estatus' => '1'];
        $this->get(route('servicios-programados.index', $params))->assertOk()
            ->assertViewHas('asignaciones', fn ($items) => $items->pluck('id')->all() === [$a['assignment']->id]);
        $this->get(route('servicios-programados.index', ['estatus' => '0']))->assertOk()
            ->assertViewHas('asignaciones', fn ($items) => $items->pluck('id')->all() === [$this->items['B']['assignment']->id]);
        $this->get(route('servicios-programados.index', ['proyecto_id' => $a['project']->id, 'sector_id' => $this->items['B']['sector']->id]))->assertOk()
            ->assertViewHas('asignaciones', fn ($items) => $items->count() === 0)->assertSee('No hay servicios entregados registrados que coincidan con los filtros.');
    }

    public function test_historical_parent_states_and_legacy_assignments_without_sector_are_visible(): void
    {
        $b = $this->items['B'];
        $b['project']->update(['estatus' => false]);
        $b['assignedIndicator']->update(['estatus' => false, 'sector_proyecto_id' => null]);
        $b['assignedActivity']->update(['estatus' => false]);
        $this->get(route('servicios-programados.index', ['proyecto_id' => $b['project']->id]))->assertOk()
            ->assertViewHas('asignaciones', fn ($items) => $items->count() === 1)
            ->assertSee('Proyecto inactivo')->assertSee('Indicador inactivo')->assertSee('Actividad inactiva')->assertSee('Sin sector asignado');
    }

    public function test_deliveries_use_actual_beneficiaries_and_period_and_date_filters_in_screen_and_excel(): void
    {
        $report = $this->record($this->items['A']['assignment'], $this->admin, 4);
        $report->update(['report_date' => '2026-10-03', 'reporting_period' => '2026-10', 'total_beneficiaries' => 999]);
        $params = ['reporting_period' => ['2026-10'], 'from' => '2026-10-01', 'to' => '2026-10-05'];
        $response = $this->get(route('servicios-programados.index', $params))->assertOk();
        $items = $response->viewData('asignaciones');
        $this->assertCount(1, $items);
        $this->assertSame(4, (int) $items->first()->beneficiaries_count);
        $this->assertSame(1, $items->first()->reports_count);
        $response->assertSee('03/10/2026')->assertSee('Ver beneficiarios');
        $this->assertStringContainsString(e(route('reports.index', [
            'servicio_actividad_id' => $this->items['A']['assignment']->id, 'reported' => '',
            'reporting_period' => $params['reporting_period'], 'from' => $params['from'], 'to' => $params['to'],
        ])), $response->getContent());
        $book = $this->load($this->get(route('servicios-programados.export', $params))->assertOk());
        try {
            $sheet = $book->getActiveSheet();
            $this->assertSame(4, $sheet->getHighestDataRow());
            $this->assertSame(4, $sheet->getCell('H4')->getValue());
            $this->assertSame(1, $sheet->getCell('J4')->getValue());
            $this->assertSame('2026-10-03', $sheet->getCell('N4')->getValue());
            $this->assertSame('Octubre 2026', $book->getSheetByName('Filtros')->getCell('B8')->getValue());
        } finally {
            $book->disconnectWorksheets();
        }
        $this->get(route('servicios-programados.index', ['reporting_period' => ['2026-09', '2026-10']]))->assertOk()
            ->assertViewHas('asignaciones', fn ($items) => $items->sum('beneficiaries_count') === 9);
        $this->getJson(route('servicios-programados.index', ['from' => '2026-10-05', 'to' => '2026-10-01']))
            ->assertUnprocessable()->assertJsonValidationErrors('to');
        $this->get(route('servicios-programados.index', ['to' => '2026-09-30']))->assertOk()
            ->assertViewHas('asignaciones', fn ($items) => $items->sum('beneficiaries_count') === 5);
    }

    public function test_no_beneficiaries_means_no_recorded_delivery_and_permissions_limit_deliveries(): void
    {
        $empty = $this->record($this->items['C']['assignment'], $this->admin, 0);
        $this->get(route('servicios-programados.index'))->assertOk()
            ->assertViewHas('asignaciones', fn ($items) => $items->count() === 2)->assertDontSee('Orientación sin cantidad');
        $owner = User::factory()->create(['role' => 'reporter']);
        $owner->givePermissionTo(['ver informes por servicios', 'exportar informes por servicios excel']);
        $this->record($this->items['A']['assignment'], $owner, 1);
        $response = $this->actingAs($owner)->get(route('servicios-programados.index'))->assertOk()->assertDontSee('PROY-B');
        $this->assertCount(1, $response->viewData('asignaciones'));
        $this->assertSame(1, (int) $response->viewData('asignaciones')->first()->beneficiaries_count);
        $this->assertSame(1, $response->viewData('asignaciones')->first()->reports_count);
        $book = $this->load($this->get(route('servicios-programados.export'))->assertOk());
        try {
            $this->assertSame(4, $book->getActiveSheet()->getHighestDataRow());
            $this->assertSame(1, $book->getActiveSheet()->getCell('H4')->getValue());
        } finally {
            $book->disconnectWorksheets();
        }
    }

    public function test_navigation_links_to_report_and_access_requires_explicit_permission(): void
    {
        $this->get(route('servicios.index'))->assertOk()->assertSee(route('servicios-programados.index'), false);
        $response = $this->get(route('servicios-programados.index'))->assertOk();
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        $xpath = new \DOMXPath($dom);
        $this->assertSame(1, $xpath->query('//*[@id="navbar-nav"]//a[@href="'.route('servicios-programados.index').'" and @aria-current="page"]')->length);
        $this->assertSame('/informes-por-servicios', parse_url(route('servicios-programados.index'), PHP_URL_PATH));
        $this->assertSame(1, $xpath->query('//*[@id="sidebarReports"]//a[@href="'.route('servicios-programados.index').'" and @aria-current="page"]')->length);
        $this->assertSame(1, $xpath->query('//a[@aria-controls="sidebarReports" and contains(concat(" ", normalize-space(@class), " "), " active ")]')->length);
        $this->assertSame(0, $xpath->query('//*[@id="sidebarConfiguration"]//a[@href="'.route('servicios-programados.index').'"]')->length);
        $this->assertSame(0, $xpath->query('//a[@aria-controls="sidebarConfiguration" and contains(concat(" ", normalize-space(@class), " "), " active ")]')->length);
        foreach (['reporter', 'coordinator'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->get(route('servicios-programados.index'))->assertForbidden();
            $this->get(route('servicios-programados.export'))->assertForbidden();
            $this->get(route('reports.index'))->assertOk()->assertDontSee('Informes por Servicios');
        }
    }

    public function test_exact_assignment_drilldown_does_not_mix_same_service_in_other_projects_and_is_carried_to_exports(): void
    {
        $id = $this->items['A']['assignment']->id;
        $params = ['servicio_actividad_id' => $id, 'reported' => '', 'reporting_period' => ''];
        $page = $this->get(route('reports.index', $params))->assertOk()->assertSee('Registros filtrados por servicio:')
            ->assertSee('Kit compartido')->assertSee('name="servicio_actividad_id" value="'.$id.'"', false)
            ->assertViewHas('filters', fn ($filters) => (int) $filters['servicio_actividad_id'] === $id);
        $this->getJson(route('reports.index', $params + ['draw' => 1]))->assertOk()->assertJsonPath('recordsFiltered', 3)
            ->assertJsonCount(3, 'data')->assertDontSee('PROY-B');
        $export = $this->getJson(route('reports.index', $params + ['draw' => 1, 'export_type' => 'excel']))->assertOk()->assertJsonCount(3, 'data');
        $this->assertStringNotContainsString('PROY-B', json_encode($export->json('data')));
        $csv = $this->get(route('reports.export', $params))->assertOk()->streamedContent();
        $this->assertStringNotContainsString('PROY-B', $csv);
        $this->assertStringContainsString('PROY-A', $csv);
        $this->getJson(route('reports.index', ['servicio_actividad_id' => $this->items['C']['assignment']->id, 'draw' => 1]))->assertOk()->assertJsonPath('recordsFiltered', 0);
    }

    public function test_report_service_filter_respects_reporter_visibility_and_does_not_reveal_unrelated_configuration(): void
    {
        $owner = User::factory()->create(['role' => 'reporter']);
        $this->record($this->items['A']['assignment'], $owner, 1);
        $this->actingAs($owner)->get(route('reports.index', ['servicio_actividad_id' => $this->items['A']['assignment']->id]))->assertOk()
            ->assertViewHas('reports', fn ($reports) => $reports->count() === 1)->assertSee('Kit compartido');
        $this->get(route('reports.index', ['servicio_actividad_id' => $this->items['C']['assignment']->id]))->assertOk()
            ->assertViewHas('selectedService', null)->assertViewHas('reports', fn ($reports) => $reports->isEmpty())
            ->assertDontSee('Orientación sin cantidad')->assertDontSee('Ver configuración');
    }

    public function test_excel_exports_actual_service_attendances_and_dates_safely(): void
    {
        $book = $this->load($this->get(route('servicios-programados.export'))->assertOk()->assertDownload());
        try {
            $sheet = $book->getSheetByName('Informes por Servicios');
            $this->assertSame(5, $sheet->getHighestDataRow());
            $rows = array_column(array_slice($sheet->toArray(null, true, false), 3), null, 0);
            $this->assertSame(3, $rows[$this->items['A']['assignment']->id][7]);
            $this->assertSame(2, $rows[$this->items['B']['assignment']->id][7]);
            $this->assertArrayNotHasKey($this->items['C']['assignment']->id, $rows);
            $this->assertSame('2026-09-15', $rows[$this->items['A']['assignment']->id][13]);
            $this->assertSame(2, $rows[$this->items['A']['assignment']->id][9]);
            $this->assertSame(DataType::TYPE_NUMERIC, $sheet->getCell('H4')->getDataType());
            $this->assertSame('A3:O5', $sheet->getAutoFilter()->getRange());
        } finally {
            $book->disconnectWorksheets();
        }
        $service = $this->items['B']['assignment']->servicio;
        $service->update(['nombre' => '=1+1', 'descripcion' => '+SUM(1,2)']);
        $book = $this->load($this->get(route('servicios-programados.export', ['estatus' => '0']))->assertOk());
        try {
            $sheet = $book->getActiveSheet();
            $this->assertSame(4, $sheet->getHighestDataRow());
            $this->assertSame('=1+1', $sheet->getCell('B4')->getValue());
            $this->assertSame(DataType::TYPE_STRING, $sheet->getCell('B4')->getDataType());
            $this->assertSame('+SUM(1,2)', $sheet->getCell('C4')->getValue());
            $this->assertSame('Inactivo', $book->getSheetByName('Filtros')->getCell('B7')->getValue());
        } finally {
            $book->disconnectWorksheets();
        }
    }

    public function test_datatable_receives_all_filtered_assignments_and_export_includes_all_pages(): void
    {
        for ($i = 0; $i < 23; $i++) {
            $service = Servicio::create(['nombre' => 'Servicio adicional '.$i]);
            $assignment = ServicioActividad::create(['actividad_indicador_id' => $this->items['A']['assignedActivity']->id, 'servicio_id' => $service->id, 'estatus' => true]);
            $this->record($assignment, $this->admin, 1);
        }
        $params = ['proyecto_id' => $this->items['A']['project']->id, 'page' => 2];
        $page = $this->get(route('servicios-programados.index', $params))->assertOk();
        $this->assertCount(24, $page->viewData('asignaciones'));
        $page->assertDontSee('class="pagination"', false);
        $page->assertSee('proyecto_id='.$params['proyecto_id'], false);
        $book = $this->load($this->get(route('servicios-programados.export', $params))->assertOk());
        try {
            $this->assertSame(27, $book->getActiveSheet()->getHighestDataRow());
            $this->assertStringNotContainsString('PROY-B', json_encode($book->getActiveSheet()->toArray()));
        } finally {
            $book->disconnectWorksheets();
        }
    }

    public function test_empty_results_can_be_exported_without_inventing_assignments(): void
    {
        $book = $this->load($this->get(route('servicios-programados.export', [
            'proyecto_id' => $this->items['A']['project']->id, 'sector_id' => $this->items['B']['sector']->id,
        ]))->assertOk()->assertDownload());
        try {
            $this->assertSame(3, $book->getActiveSheet()->getHighestDataRow());
            $this->assertSame('A3:O3', $book->getActiveSheet()->getAutoFilter()->getRange());
        } finally {
            $book->disconnectWorksheets();
        }
    }

    public function test_excel_reads_more_than_one_batch_without_losing_or_duplicating_assignments(): void
    {
        for ($i = 0; $i < 253; $i++) {
            $service = Servicio::create(['nombre' => 'Servicio de lote '.$i]);
            $assignment = ServicioActividad::create(['actividad_indicador_id' => $this->items['A']['assignedActivity']->id,
                'servicio_id' => $service->id, 'estatus' => true]);
            $this->record($assignment, $this->admin, 1);
        }
        $book = $this->load($this->get(route('servicios-programados.export'))->assertOk());
        try {
            $sheet = $book->getActiveSheet();
            $ids = array_column(array_slice($sheet->toArray(null, true, false), 3), 0);
            $this->assertCount(255, $ids);
            $this->assertCount(255, array_unique($ids));
        } finally {
            $book->disconnectWorksheets();
        }
    }

    public function test_invalid_filters_are_rejected_and_export_requires_its_permission(): void
    {
        foreach (['proyecto_id', 'sector_id', 'indicador_id', 'actividad_id', 'servicio_id'] as $field) {
            foreach (['missing', '999999', ['nested']] as $value) {
                foreach (['servicios-programados.index', 'servicios-programados.export'] as $route) {
                    $this->getJson(route($route, [$field => $value]))->assertUnprocessable()->assertJsonValidationErrors($field);
                }
            }
        }
        $this->getJson(route('servicios-programados.index', ['estatus' => 'invalid']))->assertUnprocessable()->assertJsonValidationErrors('estatus');
        $this->getJson(route('reports.index', ['servicio_actividad_id' => ['nested']]))->assertUnprocessable()->assertJsonValidationErrors('servicio_actividad_id');
        $this->getJson(route('reports.export', ['servicio_actividad_id' => '999999']))->assertUnprocessable()->assertJsonValidationErrors('servicio_actividad_id');
        $role = Role::findOrCreate('service-config-viewer', 'web');
        $viewer = User::factory()->create(['role' => $role->name]);
        $viewer->givePermissionTo('ver informes por servicios');
        $this->actingAs($viewer->fresh())->get(route('servicios-programados.index'))->assertOk()->assertDontSee('Exportar Excel')
            ->assertDontSee('Ver configuración')->assertDontSee('Catálogo de servicios');
        $this->get(route('servicios-programados.export'))->assertForbidden();
        $viewer->givePermissionTo('exportar registros excel');
        $this->actingAs($viewer->fresh())->get(route('servicios-programados.export'))->assertForbidden();
        $viewer->givePermissionTo('exportar informes por servicios excel');
        $this->actingAs($viewer->fresh())->get(route('servicios-programados.export'))->assertOk();
    }

    public function test_export_matches_datatable_search_selection_and_rechecks_filters(): void
    {
        $ids = [$this->items['A']['assignment']->id, $this->items['B']['assignment']->id];
        $book = $this->load($this->post(route('servicios-programados.export'), [
            'table_selection' => '1', 'assignment_ids_json' => json_encode($ids), 'proyecto_id' => $this->items['A']['project']->id,
        ])->assertOk()->assertDownload());
        try {
            $this->assertSame(4, $book->getActiveSheet()->getHighestDataRow());
            $this->assertSame($ids[0], $book->getActiveSheet()->getCell('A4')->getValue());
        } finally {
            $book->disconnectWorksheets();
        }
        $book = $this->load($this->post(route('servicios-programados.export'), ['table_selection' => '1'])->assertOk());
        try {
            $this->assertSame(3, $book->getActiveSheet()->getHighestDataRow());
        } finally {
            $book->disconnectWorksheets();
        }
        $this->postJson(route('servicios-programados.export'), ['assignment_ids' => ['invalid']])
            ->assertUnprocessable()->assertJsonValidationErrors('assignment_ids.0');
        foreach (['invalid', '"3"', '{"id":3}'] as $json) {
            $this->postJson(route('servicios-programados.export'), ['assignment_ids_json' => $json])
                ->assertUnprocessable()->assertJsonValidationErrors('assignment_ids_json');
        }
    }

    public function test_permissions_are_assignable_protected_and_separate_from_administration(): void
    {
        $viewer = User::factory()->create(['role' => 'reporter']);
        $viewer->givePermissionTo('exportar informes por servicios excel');
        $this->actingAs($viewer->fresh())->get(route('servicios-programados.export'))->assertForbidden();
        $viewer->givePermissionTo('ver informes por servicios');
        $this->actingAs($viewer->fresh())->get(route('servicios-programados.index'))->assertOk()->assertSee('Exportar Excel')
            ->assertDontSee('Ver configuración')->assertViewHas('asignaciones', fn ($items) => $items->sum('reports_count') === 0);
        $this->get(route('servicios-programados.export'))->assertOk();

        $this->actingAs($this->admin);
        foreach (['ver informes por servicios', 'exportar informes por servicios excel'] as $name) {
            $permission = Permission::findByName($name, 'web');
            $this->delete(route('permissions.destroy', $permission))->assertRedirect();
            $this->assertDatabaseHas('permissions', ['id' => $permission->id, 'name' => $name]);
            $this->put(route('permissions.update', $permission), ['name' => 'renombrado'])->assertRedirect();
            $this->assertSame($name, $permission->fresh()->name);
        }
        $this->admin->revokePermissionTo('ver informes por servicios');
        Role::findByName('admin', 'web')->revokePermissionTo('ver informes por servicios');
        $this->actingAs($this->admin->fresh())->get(route('servicios-programados.index'))->assertForbidden();
    }

    private function load(TestResponse $response): Spreadsheet
    {
        $path = tempnam(sys_get_temp_dir(), 'sia-programmed-services-');
        try {
            file_put_contents($path, $response->streamedContent());

            return IOFactory::load($path);
        } finally {
            unlink($path);
        }
    }
}
