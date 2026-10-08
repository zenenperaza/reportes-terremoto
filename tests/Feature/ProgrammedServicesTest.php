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
use App\Models\ReportingPeriod;
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
        $response = $this->get(route('servicios-programados.index', ['vista' => 'resumen']))->assertOk()
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
            $this->get(route('servicios-programados.index', ['vista' => 'resumen', $field => $a[$key]->id]))->assertOk()
                ->assertViewHas('asignaciones', fn ($items) => $items->pluck('id')->all() === [$a['assignment']->id]);
        }
        $params = ['proyecto_id' => $a['project']->id, 'sector_id' => $a['sector']->id, 'indicador_id' => $a['indicator']->id,
            'actividad_id' => $a['activity']->id, 'servicio_id' => $a['assignment']->servicio_id, 'estatus' => '1'];
        $this->get(route('servicios-programados.index', $params + ['vista' => 'resumen']))->assertOk()
            ->assertViewHas('asignaciones', fn ($items) => $items->pluck('id')->all() === [$a['assignment']->id]);
        $this->get(route('servicios-programados.index', ['vista' => 'resumen', 'estatus' => '0']))->assertOk()
            ->assertViewHas('asignaciones', fn ($items) => $items->pluck('id')->all() === [$this->items['B']['assignment']->id]);
        $this->get(route('servicios-programados.index', ['vista' => 'resumen', 'proyecto_id' => $a['project']->id, 'sector_id' => $this->items['B']['sector']->id]))->assertOk()
            ->assertViewHas('asignaciones', fn ($items) => $items->count() === 0)->assertSee('No hay servicios entregados registrados que coincidan con los filtros.');
    }

    public function test_historical_parent_states_and_legacy_assignments_without_sector_are_visible(): void
    {
        $b = $this->items['B'];
        $b['project']->update(['estatus' => false]);
        $b['assignedIndicator']->update(['estatus' => false, 'sector_proyecto_id' => null]);
        $b['assignedActivity']->update(['estatus' => false]);
        $this->get(route('servicios-programados.index', ['vista' => 'resumen', 'proyecto_id' => $b['project']->id]))->assertOk()
            ->assertViewHas('asignaciones', fn ($items) => $items->count() === 1)
            ->assertSee('Proyecto inactivo')->assertSee('Indicador inactivo')->assertSee('Actividad inactiva')->assertSee('Sin sector asignado');
    }

    public function test_deliveries_use_actual_beneficiaries_and_period_and_date_filters_in_screen_and_excel(): void
    {
        $report = $this->record($this->items['A']['assignment'], $this->admin, 4);
        $report->update(['report_date' => '2026-10-03', 'reporting_period' => '2026-10', 'total_beneficiaries' => 999]);
        $params = ['reporting_period' => ['2026-10'], 'from' => '2026-10-03', 'to' => '2026-10-03'];
        $response = $this->get(route('servicios-programados.index', $params + ['vista' => 'resumen']))->assertOk();
        $items = $response->viewData('asignaciones');
        $this->assertCount(1, $items);
        $this->assertSame(4, (int) $items->first()->beneficiaries_count);
        $this->assertSame(1, $items->first()->reports_count);
        $response->assertSee('03/10/2026')->assertSee('Ver beneficiarios');
        $this->assertStringContainsString(e(route('reports.index', [
            'servicio_actividad_id' => $this->items['A']['assignment']->id, 'reported' => '',
            'reporting_period' => $params['reporting_period'], 'from' => $params['from'], 'to' => $params['to'],
        ])), $response->getContent());
        $book = $this->load($this->get(route('servicios-programados.export', $params + ['vista' => 'resumen']))->assertOk());
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
        $this->get(route('servicios-programados.index', ['vista' => 'resumen', 'reporting_period' => ['2026-09', '2026-10']]))->assertOk()
            ->assertViewHas('asignaciones', fn ($items) => $items->sum('beneficiaries_count') === 9);
        $this->getJson(route('servicios-programados.index', ['vista' => 'resumen', 'from' => '2026-10-05', 'to' => '2026-10-01']))
            ->assertUnprocessable()->assertJsonValidationErrors('to');
        $this->get(route('servicios-programados.index', ['vista' => 'resumen', 'to' => '2026-09-30']))->assertOk()
            ->assertViewHas('asignaciones', fn ($items) => $items->sum('beneficiaries_count') === 5);
    }

    public function test_no_beneficiaries_means_no_recorded_delivery_and_permissions_limit_deliveries(): void
    {
        $empty = $this->record($this->items['C']['assignment'], $this->admin, 0);
        $this->get(route('servicios-programados.index', ['vista' => 'resumen']))->assertOk()
            ->assertViewHas('asignaciones', fn ($items) => $items->count() === 2)->assertDontSee('Orientación sin cantidad');
        $owner = User::factory()->create(['role' => 'reporter']);
        $owner->givePermissionTo(['ver informes por servicios', 'exportar informes por servicios excel']);
        $this->record($this->items['A']['assignment'], $owner, 1);
        $response = $this->actingAs($owner)->get(route('servicios-programados.index', ['vista' => 'resumen']))->assertOk()->assertDontSee('PROY-B');
        $this->assertCount(1, $response->viewData('asignaciones'));
        $this->assertSame(1, (int) $response->viewData('asignaciones')->first()->beneficiaries_count);
        $this->assertSame(1, $response->viewData('asignaciones')->first()->reports_count);
        $book = $this->load($this->get(route('servicios-programados.export', ['vista' => 'resumen']))->assertOk());
        try {
            $this->assertSame(4, $book->getActiveSheet()->getHighestDataRow());
            $this->assertSame(1, $book->getActiveSheet()->getCell('H4')->getValue());
        } finally {
            $book->disconnectWorksheets();
        }
    }

    public function test_period_dates_span_actual_months_and_protect_screen_export_and_endpoint(): void
    {
        foreach (['2026-10-02', '2026-11-04'] as $date) {
            $this->record($this->items['A']['assignment'], $this->admin, 1)
                ->update(['report_date' => $date, 'reporting_period' => '2026-10']);
        }
        // A record without services must not broaden the period range.
        $unrelated = $this->record($this->items['A']['assignment'], $this->admin, 1);
        $unrelated->update(['report_date' => '2026-12-31', 'reporting_period' => '2026-10']);
        $unrelated->serviciosActividad()->detach();
        // Empty records likewise do not establish delivery dates.
        $this->record($this->items['C']['assignment'], $this->admin, 0)
            ->update(['report_date' => '2026-01-01', 'reporting_period' => '2026-10']);
        $params = ['reporting_period' => ['2026-10']];
        $this->getJson(route('servicios-programados.dates', $params + ['from' => 'invalid']))->assertOk()
            ->assertJsonPath('attention.min', '2026-10-02')->assertJsonPath('attention.max', '2026-11-04');
        $this->get(route('servicios-programados.index', $params + ['vista' => 'resumen']))->assertOk()
            ->assertSee('Disponible: 02/10/2026 al 04/11/2026.')
            ->assertSee('min="2026-10-02" max="2026-11-04"', false)
            ->assertSee('js/report-period-dates.js')->assertSee('data-period-date-export', false);
        $this->getJson(route('servicios-programados.dates', ['reporting_period' => ['2026-09', '2026-10']]))
            ->assertOk()->assertJsonPath('attention.min', '2026-09-15')->assertJsonPath('attention.max', '2026-11-04');
        foreach (['2026-10-01', '2026-11-05'] as $outside) {
            foreach (['from', 'to'] as $field) {
                foreach (['servicios-programados.index', 'servicios-programados.export'] as $route) {
                    $this->getJson(route($route, $params + [$field => $outside]))
                        ->assertUnprocessable()->assertJsonValidationErrors($field);
                }
            }
        }
        $this->get(route('servicios-programados.index', $params + ['from' => '2026-10-02', 'to' => '2026-11-04']))
            ->assertOk()->assertViewHas('asignaciones', fn ($items) => $items->first()->reports_count === 2);
        $empty = ['reporting_period' => ['2028-01']];
        $this->getJson(route('servicios-programados.dates', $empty))->assertOk()
            ->assertJsonPath('attention.min', null)->assertJsonPath('attention.max', null);
        $this->get(route('servicios-programados.index', $empty))->assertOk()->assertSee('Sin fechas registradas disponibles.');
        $this->getJson(route('servicios-programados.export', $empty + ['from' => '2028-01-01']))
            ->assertUnprocessable()->assertJsonValidationErrors('from');
        $this->getJson(route('servicios-programados.dates', ['reporting_period' => ['invalid']]))
            ->assertUnprocessable()->assertJsonValidationErrors('reporting_period');
        $owner = User::factory()->create(['role' => 'reporter']);
        $this->actingAs($owner)->getJson(route('servicios-programados.dates', $params))->assertForbidden();
        $owner->givePermissionTo('ver informes por servicios');
        $this->actingAs($owner->fresh())->getJson(route('servicios-programados.dates', $params))->assertOk()
            ->assertJsonPath('attention.min', null)->assertJsonPath('attention.max', null);
        $this->record($this->items['A']['assignment'], $owner, 1)
            ->update(['report_date' => '2026-10-20', 'reporting_period' => '2026-10']);
        $this->getJson(route('servicios-programados.dates', $params))->assertOk()
            ->assertJsonPath('attention.min', '2026-10-20')->assertJsonPath('attention.max', '2026-10-20');
    }

    public function test_navigation_links_to_report_and_access_requires_explicit_permission(): void
    {
        $this->get(route('servicios.index'))->assertOk()->assertSee(route('servicios-programados.index'), false);
        $response = $this->get(route('servicios-programados.index', ['vista' => 'resumen']))->assertOk();
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        $xpath = new \DOMXPath($dom);
        $this->assertSame(1, $xpath->query('//*[@id="navbar-nav"]//a[@href="'.route('servicios-programados.index').'" and @aria-current="page"]')->length);
        $this->assertSame('/informes-por-servicios', parse_url(route('servicios-programados.index', ['vista' => 'resumen']), PHP_URL_PATH));
        $this->assertSame(1, $xpath->query('//*[@id="sidebarReports"]//a[@href="'.route('servicios-programados.index').'" and @aria-current="page"]')->length);
        $this->assertSame(1, $xpath->query('//a[@aria-controls="sidebarReports" and contains(concat(" ", normalize-space(@class), " "), " active ")]')->length);
        $this->assertSame(0, $xpath->query('//*[@id="sidebarConfiguration"]//a[@href="'.route('servicios-programados.index').'"]')->length);
        $this->assertSame(0, $xpath->query('//a[@aria-controls="sidebarConfiguration" and contains(concat(" ", normalize-space(@class), " "), " active ")]')->length);
        foreach (['reporter', 'coordinator'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->get(route('servicios-programados.index', ['vista' => 'resumen']))->assertForbidden();
            $this->get(route('servicios-programados.export', ['vista' => 'resumen']))->assertForbidden();
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

    public function test_service_actions_require_explicit_permissions_even_for_an_admin_role(): void
    {
        $activity = $this->items['A']['assignedActivity'];
        $assignment = $this->items['A']['assignment'];
        $this->admin->revokePermissionTo('administrar sistema');
        Role::findByName('admin', 'web')->revokePermissionTo('administrar sistema');
        $this->actingAs($this->admin->fresh())->get(route('servicios-programados.index', ['vista' => 'resumen']))->assertOk()
            ->assertDontSee('Ver configuración')->assertDontSee('Catálogo de servicios')->assertSee('Ver beneficiarios');
        $this->get(route('actividad-indicador.servicios.index', $activity))->assertForbidden();
        $this->post(route('actividad-indicador.servicios.store', $activity), [])->assertForbidden();
        $this->put(route('servicio-actividad.update', $assignment), ['estatus' => '0'])->assertForbidden();
        $this->delete(route('servicio-actividad.destroy', $assignment))->assertForbidden();
        $this->assertTrue((bool) $assignment->fresh()->estatus);
        $this->admin->givePermissionTo('administrar sistema');
        $this->actingAs($this->admin->fresh())->get(route('servicios-programados.index', ['vista' => 'resumen']))->assertOk()->assertSee('Ver configuración');
        $this->get(route('actividad-indicador.servicios.index', $activity))->assertOk();

        $this->admin->revokePermissionTo('ver detalle de registros');
        Role::findByName('admin', 'web')->revokePermissionTo('ver detalle de registros');
        $this->actingAs($this->admin->fresh())->get(route('servicios-programados.index', ['vista' => 'resumen']))->assertOk()->assertDontSee('Ver beneficiarios');
        $params = ['servicio_actividad_id' => $assignment->id];
        $this->get(route('reports.index', $params))->assertForbidden();
        $this->getJson(route('reports.index', $params + ['draw' => 1]))->assertForbidden();
        $this->get(route('reports.export', $params))->assertForbidden();
    }

    public function test_service_beneficiaries_need_list_and_detail_permissions_and_edit_is_independent(): void
    {
        $role = Role::findOrCreate('service-reader', 'web');
        $viewer = User::factory()->create(['role' => $role->name, 'countrywide_access' => true]);
        $viewer->givePermissionTo('ver informes por servicios');
        $report = $this->record($this->items['A']['assignment'], $viewer, 1);
        $report->update(['status' => 'submitted']);
        $person = $report->beneficiaries()->first();
        $params = ['servicio_actividad_id' => $this->items['A']['assignment']->id];
        $this->actingAs($viewer->fresh())->get(route('servicios-programados.index', ['vista' => 'resumen']))->assertOk()
            ->assertDontSee('Ver configuración')->assertDontSee('Ver beneficiarios');
        $this->get(route('reports.index', $params))->assertForbidden();
        $this->get(route('reports.show', $report))->assertForbidden();

        $viewer->givePermissionTo('ver detalle de registros');
        $this->actingAs($viewer->fresh())->get(route('servicios-programados.index', ['vista' => 'resumen']))->assertOk()->assertDontSee('Ver beneficiarios');
        $this->get(route('reports.index', $params))->assertForbidden();
        $viewer->revokePermissionTo('ver detalle de registros');
        $viewer->givePermissionTo('solo ver registros');
        $this->actingAs($viewer->fresh())->get(route('servicios-programados.index', ['vista' => 'resumen']))->assertOk()->assertDontSee('Ver beneficiarios');
        $this->get(route('reports.index'))->assertOk();
        foreach ([[], ['draw' => 1], ['draw' => 1, 'export_type' => 'copy']] as $tableParams) {
            $this->getJson(route('reports.index', $params + $tableParams))->assertForbidden();
        }
        $this->get(route('reports.show', $report))->assertForbidden();

        $viewer->givePermissionTo('ver detalle de registros');
        $this->actingAs($viewer->fresh())->get(route('servicios-programados.index', ['vista' => 'resumen']))->assertOk()->assertSee('Ver beneficiarios');
        $this->get(route('reports.index', $params))->assertOk();
        $this->get(route('reports.show', $report))->assertOk()->assertViewHas('canEditBeneficiaries', false)
            ->assertDontSee('aria-label="Editar beneficiario"', false);
        $editUrl = route('reports.edit', ['report' => $report, 'beneficiary' => $person->id]);
        $this->get($editUrl)->assertForbidden();
        $this->putJson(route('beneficiaries.update', $person), [])->assertForbidden();

        $viewer->givePermissionTo('editar beneficiarios');
        $this->actingAs($viewer->fresh())->get(route('reports.show', $report))->assertOk()->assertViewHas('canEditBeneficiaries', true)
            ->assertSee('aria-label="Editar beneficiario"', false);
        $this->get($editUrl)->assertOk()->assertViewHas('editingBeneficiary', fn ($item) => $item->id === $person->id);
        $other = Report::where('user_id', $this->admin->id)->first();
        $this->get(route('reports.show', $other))->assertOk()->assertViewHas('canEditBeneficiaries', false);
        $this->get(route('reports.edit', ['report' => $other, 'beneficiary' => $other->beneficiaries()->first()->id]))->assertForbidden();
        ReportingPeriod::updateOrCreate(['period' => $report->reporting_period], ['is_closed' => true]);
        $this->get(route('reports.show', $report))->assertOk()->assertViewHas('canEditBeneficiaries', false);
        $this->get($editUrl)->assertStatus(409);
    }

    public function test_excel_exports_actual_service_attendances_and_dates_safely(): void
    {
        $book = $this->load($this->get(route('servicios-programados.export', ['vista' => 'resumen']))->assertOk()->assertDownload());
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
        $book = $this->load($this->get(route('servicios-programados.export', ['vista' => 'resumen', 'estatus' => '0']))->assertOk());
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
        $page = $this->get(route('servicios-programados.index', $params + ['vista' => 'resumen']))->assertOk();
        $this->assertCount(24, $page->viewData('asignaciones'));
        $page->assertDontSee('class="pagination"', false);
        $page->assertSee('proyecto_id='.$params['proyecto_id'], false);
        $book = $this->load($this->get(route('servicios-programados.export', $params + ['vista' => 'resumen']))->assertOk());
        try {
            $this->assertSame(27, $book->getActiveSheet()->getHighestDataRow());
            $this->assertStringNotContainsString('PROY-B', json_encode($book->getActiveSheet()->toArray()));
        } finally {
            $book->disconnectWorksheets();
        }
    }

    public function test_empty_results_can_be_exported_without_inventing_assignments(): void
    {
        $book = $this->load($this->get(route('servicios-programados.export', ['vista' => 'resumen',
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
        $book = $this->load($this->get(route('servicios-programados.export', ['vista' => 'resumen']))->assertOk());
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
        $this->getJson(route('servicios-programados.index', ['vista' => 'resumen', 'estatus' => 'invalid']))->assertUnprocessable()->assertJsonValidationErrors('estatus');
        $this->getJson(route('reports.index', ['servicio_actividad_id' => ['nested']]))->assertUnprocessable()->assertJsonValidationErrors('servicio_actividad_id');
        $this->getJson(route('reports.export', ['servicio_actividad_id' => '999999']))->assertUnprocessable()->assertJsonValidationErrors('servicio_actividad_id');
        $role = Role::findOrCreate('service-config-viewer', 'web');
        $viewer = User::factory()->create(['role' => $role->name]);
        $viewer->givePermissionTo('ver informes por servicios');
        $this->actingAs($viewer->fresh())->get(route('servicios-programados.index', ['vista' => 'resumen']))->assertOk()->assertDontSee('Exportar Excel')
            ->assertDontSee('Ver configuración')->assertDontSee('Catálogo de servicios');
        $this->get(route('servicios-programados.export', ['vista' => 'resumen']))->assertForbidden();
        $viewer->givePermissionTo('exportar registros excel');
        $this->actingAs($viewer->fresh())->get(route('servicios-programados.export', ['vista' => 'resumen']))->assertForbidden();
        $viewer->givePermissionTo('exportar informes por servicios excel');
        $this->actingAs($viewer->fresh())->get(route('servicios-programados.export', ['vista' => 'resumen']))->assertOk();
    }

    public function test_export_matches_datatable_search_selection_and_rechecks_filters(): void
    {
        $ids = [$this->items['A']['assignment']->id, $this->items['B']['assignment']->id];
        $book = $this->load($this->post(route('servicios-programados.export', ['vista' => 'resumen']), [
            'table_selection' => '1', 'assignment_ids_json' => json_encode($ids), 'proyecto_id' => $this->items['A']['project']->id,
        ])->assertOk()->assertDownload());
        try {
            $this->assertSame(4, $book->getActiveSheet()->getHighestDataRow());
            $this->assertSame($ids[0], $book->getActiveSheet()->getCell('A4')->getValue());
        } finally {
            $book->disconnectWorksheets();
        }
        $book = $this->load($this->post(route('servicios-programados.export', ['vista' => 'resumen']), ['table_selection' => '1'])->assertOk());
        try {
            $this->assertSame(3, $book->getActiveSheet()->getHighestDataRow());
        } finally {
            $book->disconnectWorksheets();
        }
        $this->postJson(route('servicios-programados.export', ['vista' => 'resumen']), ['assignment_ids' => ['invalid']])
            ->assertUnprocessable()->assertJsonValidationErrors('assignment_ids.0');
        foreach (['invalid', '"3"', '{"id":3}'] as $json) {
            $this->postJson(route('servicios-programados.export', ['vista' => 'resumen']), ['assignment_ids_json' => $json])
                ->assertUnprocessable()->assertJsonValidationErrors('assignment_ids_json');
        }
    }

    public function test_permissions_are_assignable_protected_and_separate_from_administration(): void
    {
        $viewer = User::factory()->create(['role' => 'reporter']);
        $viewer->givePermissionTo('exportar informes por servicios excel');
        $this->actingAs($viewer->fresh())->get(route('servicios-programados.export', ['vista' => 'resumen']))->assertForbidden();
        $viewer->givePermissionTo('ver informes por servicios');
        $this->actingAs($viewer->fresh())->get(route('servicios-programados.index', ['vista' => 'resumen']))->assertOk()->assertSee('Exportar Excel')
            ->assertDontSee('Ver configuración')->assertViewHas('asignaciones', fn ($items) => $items->sum('reports_count') === 0);
        $this->get(route('servicios-programados.export', ['vista' => 'resumen']))->assertOk();

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
        $this->actingAs($this->admin->fresh())->get(route('servicios-programados.index', ['vista' => 'resumen']))->assertForbidden();
    }

    private function multiServiceRecord(User $owner): Report
    {
        $thirdService = Servicio::firstOrCreate(['nombre' => 'Tercer kit']);
        $third = ServicioActividad::firstOrCreate(['actividad_indicador_id' => $this->items['A']['assignedActivity']->id,
            'servicio_id' => $thirdService->id, 'estatus' => true]);
        $report = $this->record($this->items['A']['assignment'], $owner, 2);
        $report->serviciosActividad()->attach([$this->items['C']['assignment']->id, $third->id]);
        $report->update(['reporting_period' => '2026-10', 'report_date' => '2026-10-03', 'status' => 'submitted']);

        return $report;
    }

    public function test_delivery_views_group_services_per_beneficiary_or_expand_each_service_without_changing_data(): void
    {
        $report = $this->multiServiceRecord($this->admin);
        $params = ['reporting_period' => ['2026-10']];
        $people = $report->beneficiaries()->pluck('id')->all();
        $response = $this->get(route('servicios-programados.index', $params + ['vista' => 'beneficiario']))->assertOk()
            ->assertSee('Por beneficiario')->assertSee('Por servicio')->assertSee('PERSONA CONFIDENCIAL')
            ->assertSee('data-delivery-rows="1"', false)->assertDontSee('Editar beneficiario');
        $rows = $response->viewData('entregas');
        $this->assertCount(2, $rows);
        $this->assertSame($people, $rows->pluck('beneficiary.id')->all());
        foreach ($rows as $row) {
            $this->assertCount(3, $row['services']);
        }
        $expanded = $this->get(route('servicios-programados.index', $params + ['vista' => 'servicio']))->assertOk()
            ->assertDontSee('Editar beneficiario')->viewData('entregas');
        $this->assertCount(6, $expanded);
        foreach ($people as $id) {
            $this->assertCount(3, $expanded->where('beneficiary.id', $id));
        }
        foreach ($expanded as $row) {
            $this->assertCount(1, $row['services']);
        }
        $this->assertCount(6, $expanded->pluck('key')->unique());

        foreach (['beneficiario', 'servicio'] as $mode) {
            $filtered = $this->get(route('servicios-programados.index', $params + ['vista' => $mode,
                'servicio_id' => $this->items['C']['assignment']->servicio_id, 'from' => '2026-10-03', 'to' => '2026-10-03']))->assertOk()->viewData('entregas');
            $this->assertCount(2, $filtered);
            foreach ($filtered as $row) {
                $this->assertSame([$this->items['C']['assignment']->id], $row['services']->pluck('id')->all());
            }
            $this->get(route('servicios-programados.index', $params + ['vista' => $mode, 'proyecto_id' => $this->items['B']['project']->id]))
                ->assertOk()->assertViewHas('entregas', fn ($rows) => $rows->isEmpty());
        }
        $this->get(route('servicios-programados.index', $params + ['vista' => 'resumen']))->assertOk()->assertDontSee('PERSONA CONFIDENCIAL')
            ->assertViewHas('asignaciones', fn ($items) => $items->count() === 3)->assertViewHas('entregas', fn ($rows) => $rows->isEmpty());
        $this->assertDatabaseCount('beneficiaries', 7);
        $this->assertDatabaseCount('report_servicio_actividad', 6);
    }

    public function test_delivery_views_and_excel_protect_beneficiary_permissions_and_visibility(): void
    {
        $owner = User::factory()->create(['role' => 'reporter']);
        $owner->givePermissionTo(['ver informes por servicios', 'exportar informes por servicios excel']);
        $own = $this->multiServiceRecord($owner);
        $private = $this->multiServiceRecord($this->admin);
        $private->beneficiaries()->update(['full_name' => 'PRIVATE DELIVERY']);
        foreach (['beneficiario', 'servicio'] as $mode) {
            $this->actingAs($owner->fresh())->get(route('servicios-programados.index', ['vista' => $mode]))->assertOk()
                ->assertDontSee('PRIVATE DELIVERY')->assertDontSee('Ver configuración')
                ->assertViewHas('entregas', fn ($rows) => $rows->pluck('beneficiary.report_id')->unique()->all() === [$own->id]);
        }
        $role = Role::findOrCreate('delivery-summary-reader', 'web');
        $viewer = User::factory()->create(['role' => $role->name, 'countrywide_access' => true]);
        $viewer->givePermissionTo(['ver informes por servicios', 'exportar informes por servicios excel', 'solo ver registros']);
        $this->actingAs($viewer->fresh())->get(route('servicios-programados.index'))->assertOk()->assertDontSee('value="beneficiario"', false);
        foreach (['beneficiario', 'servicio'] as $mode) {
            $this->get(route('servicios-programados.index', ['vista' => $mode]))->assertForbidden();
            $this->get(route('servicios-programados.export', ['vista' => $mode]))->assertForbidden();
        }
        $viewer->givePermissionTo('ver detalle de registros');
        $this->actingAs($viewer->fresh())->get(route('servicios-programados.index', ['vista' => 'beneficiario']))->assertOk()
            ->assertSee('Ver beneficiario')->assertDontSee('Editar beneficiario')->assertDontSee('Ver configuración');
        $viewer->givePermissionTo('editar beneficiarios');
        $this->actingAs($viewer->fresh())->get(route('servicios-programados.index', ['vista' => 'servicio']))->assertOk()->assertDontSee('Editar beneficiario');
        $this->actingAs($this->admin);
        ReportingPeriod::updateOrCreate(['period' => '2026-10'], ['is_closed' => true]);
        $this->get(route('servicios-programados.index', ['vista' => 'servicio', 'reporting_period' => ['2026-10']]))->assertOk()
            ->assertDontSee('Editar beneficiario')->assertSee('Ver beneficiario');
    }

    public function test_delivery_excel_matches_view_search_keys_filters_and_writes_names_as_text(): void
    {
        $report = $this->multiServiceRecord($this->admin);
        $person = $report->beneficiaries()->first();
        $person->update(['full_name' => '=1+1']);
        foreach (['beneficiario' => 2, 'servicio' => 6] as $mode => $count) {
            $params = ['vista' => $mode, 'reporting_period' => ['2026-10']];
            $book = $this->load($this->get(route('servicios-programados.export', $params))->assertOk());
            try {
                $sheet = $book->getActiveSheet();
                $this->assertSame(3 + $count, $sheet->getHighestDataRow());
                $this->assertSame('=1+1', $sheet->getCell('C4')->getValue());
                $this->assertSame(DataType::TYPE_STRING, $sheet->getCell('C4')->getDataType());
                $this->assertSame($mode === 'beneficiario' ? 'Por beneficiario' : 'Por servicio', $book->getSheetByName('Filtros')->getCell('B2')->getValue());
                $this->assertSame('A3:L'.(3 + $count), $sheet->getAutoFilter()->getRange());
            } finally {
                $book->disconnectWorksheets();
            }
            $key = $mode === 'beneficiario' ? (string) $person->id : $person->id.':'.$this->items['A']['assignment']->id;
            foreach ([[$key], [], ['999999:999999']] as $keys) {
                $book = $this->load($this->post(route('servicios-programados.export'), $params + [
                    'table_selection' => '1', 'row_keys_json' => json_encode($keys),
                ])->assertOk());
                try {
                    $this->assertSame($keys === [$key] ? 4 : 3, $book->getActiveSheet()->getHighestDataRow());
                } finally {
                    $book->disconnectWorksheets();
                }
            }
            // A valid beneficiary key cannot bypass an incompatible project filter.
            $book = $this->load($this->post(route('servicios-programados.export'), $params + [
                'proyecto_id' => $this->items['B']['project']->id, 'table_selection' => '1', 'row_keys_json' => json_encode([$key]),
            ])->assertOk());
            try {
                $this->assertSame(3, $book->getActiveSheet()->getHighestDataRow());
            } finally {
                $book->disconnectWorksheets();
            }
        }
        foreach (['bad json', '{"id":1}', '["bad"]', '[1]'] as $json) {
            $this->postJson(route('servicios-programados.export'), ['vista' => 'servicio', 'table_selection' => '1', 'row_keys_json' => $json])
                ->assertUnprocessable()->assertJsonValidationErrors('row_keys_json');
        }
        $this->getJson(route('servicios-programados.index', ['vista' => 'unknown']))->assertUnprocessable()->assertJsonValidationErrors('vista');
    }

    public function test_default_view_and_excel_are_per_beneficiary_and_explicit_modes_are_preserved(): void
    {
        foreach ([[], ['vista' => ''], ['reporting_period' => ['2026-09']]] as $params) {
            $this->get(route('servicios-programados.index', $params))->assertOk()
                ->assertViewHas('filters', fn ($filters) => $filters['vista'] === 'beneficiario')
                ->assertViewHas('entregas', fn ($rows) => $rows->count() === 5)
                ->assertSee('value="beneficiario" selected', false);
        }
        foreach (['resumen', 'servicio'] as $mode) {
            $this->get(route('servicios-programados.index', ['vista' => $mode]))->assertOk()
                ->assertViewHas('filters', fn ($filters) => $filters['vista'] === $mode);
        }
        $book = $this->load($this->get(route('servicios-programados.export'))->assertOk());
        try {
            $this->assertSame('Por beneficiario', $book->getSheetByName('Filtros')->getCell('B2')->getValue());
            $this->assertSame(8, $book->getActiveSheet()->getHighestDataRow());
        } finally {
            $book->disconnectWorksheets();
        }
    }

    public function test_default_falls_back_to_summary_without_both_beneficiary_permissions(): void
    {
        $role = Role::findOrCreate('default-summary-reader', 'web');
        $viewer = User::factory()->create(['role' => $role->name, 'countrywide_access' => true]);
        $viewer->givePermissionTo(['ver informes por servicios', 'exportar informes por servicios excel']);
        foreach ([null, 'solo ver registros', 'ver detalle de registros'] as $permission) {
            $viewer->syncPermissions(array_filter(['ver informes por servicios', 'exportar informes por servicios excel', $permission]));
            $this->actingAs($viewer->fresh())->get(route('servicios-programados.index'))->assertOk()
                ->assertViewHas('filters', fn ($filters) => $filters['vista'] === 'resumen')
                ->assertDontSee('PERSONA CONFIDENCIAL')->assertSee('value="resumen" selected', false);
            $this->get(route('servicios-programados.index', ['vista' => 'beneficiario']))->assertForbidden();
            $book = $this->load($this->get(route('servicios-programados.export'))->assertOk());
            try {
                $this->assertSame('Informes por Servicios', $book->getActiveSheet()->getTitle());
                $this->assertStringNotContainsString('PERSONA CONFIDENCIAL', json_encode($book->getActiveSheet()->toArray()));
            } finally {
                $book->disconnectWorksheets();
            }
        }
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
