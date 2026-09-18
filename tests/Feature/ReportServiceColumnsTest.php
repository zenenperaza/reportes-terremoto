<?php

namespace Tests\Feature;

use App\Models\Actividad;
use App\Models\ActividadIndicador;
use App\Models\Activity;
use App\Models\Donante;
use App\Models\Indicador;
use App\Models\IndicadorProyecto;
use App\Models\Municipality;
use App\Models\Parish;
use App\Models\Proyecto;
use App\Models\Report;
use App\Models\Sector;
use App\Models\Servicio;
use App\Models\State;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportServiceColumnsTest extends TestCase
{
    use RefreshDatabase;

    private function report(User $owner): Report
    {
        $state = State::create(['code' => 'VE24', 'name' => 'La Guaira']);
        $municipality = Municipality::create(['state_id' => $state->id, 'code' => 'VE2401', 'name' => 'Vargas']);
        $parish = Parish::create(['municipality_id' => $municipality->id, 'code' => 'VE240101', 'name' => 'Caraballeda']);
        $sector = Sector::create(['name' => 'Protección', 'slug' => 'proteccion', 'sort_order' => 1]);
        $legacy = Activity::create(['sector_id' => $sector->id, 'code' => 'LEGACY-01', 'title' => 'Indicador anterior', 'sort_order' => 1]);
        $donor = Donante::create(['nombre' => 'UNICEF', 'estatus' => true]);
        $project = Proyecto::create(['donante_id' => $donor->id, 'codigo' => 'PROY-COLUMNAS', 'descripcion' => 'Proyecto de prueba', 'estatus' => true]);
        $indicator = Indicador::create(['codigo' => 'IND-COLUMNAS', 'descripcion' => 'Indicador del proyecto', 'unidad_conteo' => 'Personas', 'espacio_coordinacion' => 'NNA', 'edad_desde' => 0, 'edad_hasta' => 120]);
        $assignment = IndicadorProyecto::create(['proyecto_id' => $project->id, 'indicador_id' => $indicator->id, 'estatus' => true]);
        $activity = Actividad::create(['codigo' => 'ACT-COLUMNAS', 'descripcion' => 'Actividad específica del indicador']);
        $projectActivity = ActividadIndicador::create(['indicador_proyecto_id' => $assignment->id, 'actividad_id' => $activity->id, 'estatus' => true]);
        $services = collect(['Orientación seleccionada', 'Kit seleccionado', 'Servicio no seleccionado'])->map(function ($name) use ($projectActivity) {
            $service = Servicio::create(['nombre' => $name]);

            return $projectActivity->asignacionesServicios()->create(['servicio_id' => $service->id, 'estatus' => true, 'cantidad_disponible' => 999]);
        });
        $report = Report::create([
            'user_id' => $owner->id, 'proyecto_id' => $project->id, 'indicador_proyecto_id' => $assignment->id, 'actividad_indicador_id' => $projectActivity->id,
            'report_date' => '2026-09-17', 'reporter_first_name' => 'Responsable', 'reporter_last_name' => 'Prueba', 'reporter_email' => $owner->email, 'organization' => 'ASONACOP',
            'state_id' => $state->id, 'municipality_id' => $municipality->id, 'parish_id' => $parish->id, 'installation_type' => 'Comunidad / Espacio Comunitario', 'place_name' => 'Lugar de prueba',
            'sector_id' => $sector->id, 'activity_id' => $legacy->id, 'recurrence_status' => 'nuevo', 'total_beneficiaries' => 2, 'beneficiary_breakdown' => [],
        ]);
        $report->serviciosActividad()->sync($services->take(2)->pluck('id'));
        foreach (['Persona reservada uno', 'Persona reservada dos'] as $name) {
            $report->beneficiaries()->create(['has_informed_consent' => true, 'full_name' => $name, 'national_id' => 'CEDULA-RESERVADA', 'phone' => 'TELEFONO-RESERVADO', 'age' => 12, 'sex' => 'Mujer', 'disability' => 'Ninguna', 'ethnicity' => 'Ninguna', 'pregnant_lactating' => 'N/A', 'is_recurrent' => false]);
        }

        return $report;
    }

    private function assertTableStructure(string $html, int $expectedRows): void
    {
        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument;
        $dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new \DOMXPath($dom);
        $headers = $xpath->query('//*[@id="activity-records-table"]/thead/tr/th')->length;
        $classificationHeaders = $xpath->query('//*[@id="activity-records-table"]/thead/tr/th[normalize-space()="Indicadores"]/following-sibling::th[position() <= 2]');
        $this->assertCount(2, $classificationHeaders);
        $this->assertSame('Actividades', trim($classificationHeaders->item(0)->textContent));
        $this->assertSame('4', $classificationHeaders->item(0)->getAttribute('data-priority'));
        $this->assertSame('Servicios', trim($classificationHeaders->item(1)->textContent));
        $rows = $xpath->query('//*[@id="activity-records-table"]/tbody/tr');
        $this->assertCount($expectedRows, $rows);
        foreach ($rows as $row) {
            $this->assertCount($headers, $xpath->query('./td', $row));
            $this->assertSame('2', trim($xpath->query('./td[@class="report-services-count"]', $row)->item(0)->textContent));
        }
    }

    public function test_admin_and_reporter_see_selected_hierarchy_without_counting_inventory_or_beneficiaries(): void
    {
        $owner = User::factory()->create(['role' => 'reporter']);
        $report = $this->report($owner);
        $admin = User::factory()->create(['role' => 'admin']);
        foreach ([[$admin, 2], [$owner, 1]] as [$user,$rows]) {
            $response = $this->actingAs($user)->get(route('reports.index'))->assertOk();
            $response->assertSee('responsive: true', false)->assertSee('dataTables.responsive.min.js')
                ->assertSee('autoWidth: true', false)->assertSee('report-table-card')
                ->assertSee('report-classification report-indicator')
                ->assertSee("columns: ':not(.no-export)'", false);
            $this->assertTableStructure($response->getContent(), $user->isCoordinator() ? 0 : $rows);
            if ($user->isCoordinator()) {
                $response->assertSee('serverSide: true', false)->assertDontSee('PROY-COLUMNAS');
                $data = $this->getJson(route('reports.index', ['draw' => 1, 'length' => 15]))->assertOk()
                    ->assertJsonPath('recordsTotal', 2)->assertJsonCount(2, 'data')
                    ->assertJsonPath('data.0.service_count', 2)->json('data');
                $html = json_encode($data, JSON_UNESCAPED_UNICODE);
            } else {
                $html = $response->getContent();
                $this->assertTrue($response->viewData('reports')->first()->relationLoaded('serviciosActividad'));
            }
            foreach (['PROY-COLUMNAS', 'IND-COLUMNAS', 'ACT-COLUMNAS', 'Orientación seleccionada', 'Kit seleccionado'] as $value) {
                $this->assertStringContainsString($value, $html);
            }
            $this->assertStringNotContainsString('Servicio no seleccionado', $html);
        }
        $this->assertSame(2, $report->serviciosActividad()->count());
    }

    public function test_coordinator_responsive_html_keeps_personal_fields_absent_and_respects_group_scope(): void
    {
        $owner = User::factory()->create(['role' => 'reporter']);
        $report = $this->report($owner);
        $group = UserGroup::create(['name' => 'Grupo de prueba', 'is_active' => true]);
        $owner->userGroups()->attach($group);
        $coordinator = User::factory()->create(['role' => 'coordinator']);
        $coordinator->userGroups()->attach($group);
        $response = $this->actingAs($coordinator)->get(route('reports.index'))->assertOk()
            ->assertDontSee('Persona reservada')->assertDontSee('CEDULA-RESERVADA')->assertDontSee('TELEFONO-RESERVADO')->assertDontSee('<th>Nombres</th>', false);
        $this->assertTableStructure($response->getContent(), 0);
        $data = $this->getJson(route('reports.index', ['draw' => 1]))->assertOk()->assertJsonPath('recordsTotal', 2)->json('data');
        $this->assertArrayNotHasKey('full_name', $data[0]);
        $this->assertArrayNotHasKey('national_id', $data[0]);
        $this->assertArrayNotHasKey('phone', $data[0]);
        $this->assertStringContainsString('Orientación seleccionada', $data[0]['services']);
        $this->getJson(route('reports.index', ['draw' => 2, 'search' => ['value' => 'CEDULA-RESERVADA']]))->assertOk()->assertJsonPath('recordsFiltered', 0);
        $outsider = User::factory()->create(['role' => 'reporter']);
        $this->actingAs($outsider)->get(route('reports.index'))->assertOk()->assertDontSee('Orientación seleccionada')->assertDontSee('PROY-COLUMNAS');
    }

    public function test_server_pagination_limits_rows_and_sorts_on_the_server(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $report = $this->report($admin);
        $first = $report->beneficiaries()->first();
        $first->update(['age' => 8]);
        $report->beneficiaries()->where('id', '!=', $first->id)->update(['age' => 19]);
        $this->actingAs($admin)->get(route('reports.index'))->assertOk()
            ->assertSee('serverSide: true', false)->assertDontSee('PROY-COLUMNAS');
        $params = ['draw' => 7, 'start' => 0, 'length' => 1, 'order' => [['column' => 6, 'dir' => 'asc']]];
        $page = $this->getJson(route('reports.index', $params))->assertOk()->assertJsonPath('draw', 7)
            ->assertJsonPath('recordsTotal', 2)->assertJsonPath('recordsFiltered', 2)->assertJsonCount(1, 'data');
        $this->assertStringContainsString('8 a&ntilde;os', $page->json('data.0.age'));
        $params['start'] = 1;
        $page = $this->getJson(route('reports.index', $params))->assertOk()->assertJsonCount(1, 'data');
        $this->assertStringContainsString('19 a&ntilde;os', $page->json('data.0.age'));
        $params['start'] = 99;
        $this->getJson(route('reports.index', $params))->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('recordsFiltered', 2);
        // Exercise every allowed column, including SQL aliases and the service count subquery.
        foreach (\App\Services\ReportDataTable::columns($admin) as $index => $column) {
            $this->getJson(route('reports.index', ['draw' => 1, 'order' => [['column' => $index, 'dir' => 'desc']]]))
                ->assertOk()->assertJsonCount(2, 'data');
        }
        for ($i = 0; $i < 105; $i++) {
            $first->replicate()->save();
        }
        foreach ([15, 100, -1, 100000] as $length) {
            $this->getJson(route('reports.index', ['draw' => 1, 'length' => $length]))->assertOk()
                ->assertJsonPath('recordsTotal', 107)->assertJsonCount($length === 15 ? 15 : 100, 'data');
        }
    }

    public function test_server_search_filters_validation_and_escaping(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $report = $this->report($admin);
        $this->actingAs($admin);
        foreach (['PROY-COLUMNAS', 'ACT-COLUMNAS', 'IND-COLUMNAS', 'Kit seleccionado', 'Responsable Prueba', '17/09/2026', 'CEDULA-RESERVADA', '2'] as $term) {
            $this->getJson(route('reports.index', ['draw' => 1, 'search' => ['value' => $term]]))->assertOk()->assertJsonPath('recordsFiltered', 2);
        }
        $payloads = \App\Models\AuditLog::where('route_name', 'reports.index')->pluck('request_payload')->toJson();
        $this->assertStringNotContainsString('CEDULA-RESERVADA', $payloads);
        foreach ([['state_id' => 999999], ['from' => '2026-09-18'], ['to' => '2026-09-16'], ['reported' => '1'], ['search' => ['value' => 'inexistente']]] as $filters) {
            $this->getJson(route('reports.index', ['draw' => 1] + $filters))->assertOk()
                ->assertJsonPath('recordsTotal', 2)->assertJsonPath('recordsFiltered', 0)->assertJsonCount(0, 'data');
        }
        $this->getJson(route('reports.index', ['draw' => 1, 'state_id' => $report->state_id, 'reported' => '0', 'from' => '2026-09-17', 'to' => '2026-09-17']))
            ->assertOk()->assertJsonPath('recordsFiltered', 2);
        $report->beneficiaries()->first()->forceFill(['reported_at' => '2026-09-18'])->save();
        $this->getJson(route('reports.index', ['draw' => 1, 'reported' => '1']))->assertOk()->assertJsonPath('recordsFiltered', 1);
        foreach ([['draw' => '<script>'], ['draw' => 1, 'start' => -3], ['draw' => 1, 'order' => [['column' => 0, 'dir' => 'desc; DROP TABLE users']]]] as $invalid) {
            $this->getJson(route('reports.index', $invalid))->assertUnprocessable();
        }
        $this->getJson(route('reports.index', ['draw' => 1, 'order' => [['column' => 999, 'dir' => 'asc']], 'columns' => [['data' => 'password']]]))
            ->assertOk()->assertJsonCount(2, 'data');
        $report->update(['place_name' => '<img src=x onerror=alert(1)>']);
        $response = $this->getJson(route('reports.index', ['draw' => 1]))->assertOk();
        $this->assertStringContainsString('&lt;img', $response->json('data.0.location'));
        $this->assertStringNotContainsString('<img', $response->json('data.0.location'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_server_counts_search_and_actions_respect_authorization(): void
    {
        $owner = User::factory()->create(['role' => 'reporter']);
        $report = $this->report($owner);
        $outsider = User::factory()->create(['role' => 'reporter']);
        $hidden = $report->replicate();
        $hidden->user_id = $outsider->id;
        $hidden->save();
        $hidden->beneficiaries()->create($report->beneficiaries()->first()->only(['full_name', 'age', 'sex', 'has_informed_consent', 'is_recurrent']));
        $coordinator = User::factory()->create(['role' => 'coordinator']);
        $this->actingAs($coordinator)->getJson(route('reports.index', ['draw' => 1]))->assertOk()->assertJsonPath('recordsTotal', 0)->assertJsonCount(0, 'data');
        $group = UserGroup::create(['name' => 'Equipo del consolidado', 'is_active' => true]);
        $owner->userGroups()->attach($group);
        $coordinator->userGroups()->attach($group);
        $response = $this->actingAs($coordinator->fresh())->getJson(route('reports.index', ['draw' => 1]))->assertOk()
            ->assertJsonPath('recordsTotal', 2)->assertJsonCount(2, 'data');
        $this->assertArrayNotHasKey('full_name', $response->json('data.0'));
        $coordinator->roles()->first()->revokePermissionTo('ver detalle de registros');
        $response = $this->actingAs($coordinator->fresh())->getJson(route('reports.index', ['draw' => 1]))->assertOk();
        $this->assertArrayNotHasKey('actions', $response->json('data.0'));
        $coordinator->roles()->first()->revokePermissionTo('solo ver registros');
        $this->actingAs($coordinator->fresh())->getJson(route('reports.index', ['draw' => 1]))->assertForbidden();
    }

    public function test_all_export_formats_include_all_matches_without_the_page_limit(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $report = $this->report($admin);
        $seed = $report->beneficiaries()->first()->getAttributes();
        unset($seed['id']);
        $seed['full_name'] = 'LOTE FILTRADO';
        \App\Models\Beneficiary::insert(array_fill(0, 518, $seed));
        $this->actingAs($admin)->getJson(route('reports.index', ['draw' => 1, 'length' => 15]))
            ->assertOk()->assertJsonPath('recordsFiltered', 520)->assertJsonCount(15, 'data');
        foreach (['copy', 'csv', 'excel', 'pdf', 'print'] as $format) {
            $response = $this->getJson(route('reports.index', ['draw' => 0, 'export_type' => $format, 'start' => 500, 'length' => 15]))->assertOk();
            $rows = json_decode($response->streamedContent(), true, 512, JSON_THROW_ON_ERROR)['data'];
            $this->assertCount(520, $rows);
            $this->assertSame(2, $rows[0]['service_count']);
            $this->assertArrayNotHasKey('actions', $rows[0]);
        }
        $response = $this->getJson(route('reports.index', ['draw' => 0, 'export_type' => 'excel',
            'search' => ['value' => 'LOTE FILTRADO'], 'order' => [['column' => 3, 'dir' => 'asc']],
            'state_id' => $report->state_id, 'reported' => '0', 'from' => '2026-09-17', 'to' => '2026-09-17',
        ]))->assertOk();
        $rows = json_decode($response->streamedContent(), true, 512, JSON_THROW_ON_ERROR)['data'];
        $this->assertCount(518, $rows);
        $this->assertSame('LOTE FILTRADO', $rows[0]['full_name']);
        foreach ([['search' => ['value' => 'No existe']], ['from' => '2026-09-18'], ['state_id' => 999], ['reported' => '1']] as $filter) {
            $response = $this->getJson(route('reports.index', ['draw' => 0, 'export_type' => 'csv'] + $filter))->assertOk();
            $this->assertSame([], json_decode($response->streamedContent(), true, 512, JSON_THROW_ON_ERROR)['data']);
        }
        $response = $this->getJson(route('reports.index', ['draw' => 0, 'export_type' => 'csv', 'order' => [['column' => 3, 'dir' => 'asc']]]))->assertOk();
        $rows = json_decode($response->streamedContent(), true, 512, JSON_THROW_ON_ERROR)['data'];
        $this->assertSame('LOTE FILTRADO', $rows[0]['full_name']);
        $this->assertSame('PERSONA RESERVADA UNO', $rows[519]['full_name']);
    }

    public function test_full_exports_enforce_format_permissions_and_group_privacy(): void
    {
        $owner = User::factory()->create(['role' => 'reporter']);
        $report = $this->report($owner);
        $coordinator = User::factory()->create(['role' => 'coordinator']);
        $group = UserGroup::create(['name' => 'Grupo exportador', 'is_active' => true]);
        $owner->userGroups()->attach($group);
        $coordinator->userGroups()->attach($group);
        $this->actingAs($coordinator);
        $response = $this->getJson(route('reports.index', ['draw' => 0, 'export_type' => 'csv']))->assertOk();
        $rows = json_decode($response->streamedContent(), true, 512, JSON_THROW_ON_ERROR)['data'];
        $this->assertCount(2, $rows);
        foreach (['full_name', 'national_id', 'phone', 'actions'] as $field) {
            $this->assertArrayNotHasKey($field, $rows[0]);
        }
        $coordinator->roles()->first()->revokePermissionTo(['exportar registros excel', 'exportar registros pdf']);
        foreach (['excel', 'pdf'] as $format) {
            $this->actingAs($coordinator->fresh())->getJson(route('reports.index', ['draw' => 0, 'export_type' => $format]))->assertForbidden();
        }
        $this->getJson(route('reports.index', ['draw' => 0, 'export_type' => 'invalid']))->assertUnprocessable();
        $outsider = User::factory()->create(['role' => 'coordinator']);
        $response = $this->actingAs($outsider)->getJson(route('reports.index', ['draw' => 0, 'export_type' => 'copy']))->assertOk();
        $this->assertSame([], json_decode($response->streamedContent(), true, 512, JSON_THROW_ON_ERROR)['data']);
    }

    public function test_legacy_reports_without_services_render_zero_and_csv_includes_new_columns(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $report = $this->report($admin);
        $response = $this->actingAs($admin)->get(route('reports.export'))->assertOk();
        $lines = preg_split('/\r?\n/', trim($response->streamedContent()));
        $headers = str_getcsv(array_shift($lines));
        $values = array_combine($headers, str_getcsv($lines[0]));
        $this->assertSame('PROY-COLUMNAS', $values['Código del proyecto']);
        $this->assertSame('IND-COLUMNAS', $values['Código del indicador']);
        $this->assertSame('Actividad específica del indicador', $values['Actividad']);
        $this->assertSame('2', $values['N.º de servicios del registro']);
        $this->assertStringContainsString('Kit seleccionado', $values['Servicios']);
        $report->serviciosActividad()->detach();
        $report->update(['proyecto_id' => null, 'indicador_proyecto_id' => null, 'actividad_indicador_id' => null]);
        $data = $this->getJson(route('reports.index', ['draw' => 1]))->assertOk()->assertJsonPath('data.0.service_count', 0)->json('data.0');
        $this->assertStringContainsString('LEGACY-01', $data['indicator']);
        $this->assertStringContainsString('Indicador anterior', $data['indicator']);
        $this->assertStringContainsString('Sin servicios asociados', $data['services']);
    }
}
