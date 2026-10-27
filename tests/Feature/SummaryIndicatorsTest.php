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
use App\Models\SummaryIndicator;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class SummaryIndicatorsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private array $items = [];
    private SummaryIndicator $one;
    private SummaryIndicator $two;
    private SummaryIndicator $other;
    private SummaryIndicator $privateSummary;
    private SummaryIndicator $empty;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($this->admin);
        $state = State::create(['code' => 'A', 'name' => 'Estado']);
        $municipality = Municipality::create(['state_id' => $state->id, 'code' => 'A1', 'name' => 'Municipio']);
        $parish = Parish::create(['municipality_id' => $municipality->id, 'code' => 'A11', 'name' => 'Parroquia']);
        $donor = Donante::create(['nombre' => 'Donante', 'estatus' => true]);
        $sector = Sector::create(['name' => 'Sector', 'slug' => 'sector']);
        $shared = Indicador::create(['codigo' => 'SHARED', 'descripcion' => 'Mismo catálogo en dos proyectos', 'unidad_conteo' => 'Personas', 'espacio_coordinacion' => 'NNA', 'edad_desde' => 0, 'edad_hasta' => 120]);
        $service = Servicio::create(['nombre' => 'Servicio común']);
        foreach (['A' => 2, 'B' => 3, 'C' => 4, 'EX' => 1] as $key => $people) {
            $project = $key === 'EX' ? $this->items['A']['project'] : Proyecto::create(['donante_id' => $donor->id, 'codigo' => 'PROY-'.$key, 'descripcion' => 'Proyecto '.$key, 'estatus' => true]);
            $projectSector = SectorProyecto::firstOrCreate(['proyecto_id' => $project->id, 'sector_id' => $sector->id]);
            $indicator = in_array($key, ['A', 'C']) ? $shared : Indicador::create(['codigo' => 'IND-'.$key, 'descripcion' => 'Indicador '.$key,
                'unidad_conteo' => 'Personas', 'espacio_coordinacion' => 'NNA', 'edad_desde' => 0, 'edad_hasta' => 120, 'excluir_reporte_beneficiarios' => $key === 'EX']);
            $assignment = IndicadorProyecto::create(['proyecto_id' => $project->id, 'sector_proyecto_id' => $projectSector->id, 'indicador_id' => $indicator->id, 'estatus' => true]);
            $activity = Actividad::create(['codigo' => 'ACT-'.$key, 'descripcion' => 'Actividad '.$key]);
            $assignedActivity = ActividadIndicador::create(['indicador_proyecto_id' => $assignment->id, 'actividad_id' => $activity->id, 'estatus' => true]);
            $assignedService = ServicioActividad::create(['actividad_indicador_id' => $assignedActivity->id, 'servicio_id' => $service->id, 'estatus' => true]);
            $report = Report::create(['user_id' => $this->admin->id, 'proyecto_id' => $project->id, 'indicador_proyecto_id' => $assignment->id,
                'actividad_indicador_id' => $assignedActivity->id, 'report_date' => '2026-09-15', 'reporting_period' => $key === 'C' ? '2026-11' : '2026-10',
                'reporter_first_name' => 'Prueba', 'reporter_last_name' => 'Resumen', 'reporter_email' => $this->admin->email, 'organization' => 'ASONACOP',
                'state_id' => $state->id, 'municipality_id' => $municipality->id, 'parish_id' => $parish->id, 'sector_id' => $sector->id,
                'installation_type' => 'Comunidad / Espacio Comunitario', 'place_name' => 'Lugar '.$key, 'recurrence_status' => 'nuevo', 'total_beneficiaries' => $people, 'beneficiary_breakdown' => []]);
            $report->serviciosActividad()->attach($assignedService);
            for ($i = 0; $i < $people; $i++) $report->beneficiaries()->create(['full_name' => 'PRIVADO '.$key, 'age' => 12, 'sex' => 'Mujer', 'national_id' => 'DOC-'.$key.'-'.$i, 'disability' => 'Ninguna', 'ethnicity' => 'Ninguna', 'pregnant_lactating' => 'N/A', 'is_recurrent' => false]);
            $this->items[$key] = compact('project', 'assignment', 'indicator', 'report', 'assignedService');
        }
        $this->one = SummaryIndicator::create(['proyecto_id' => $this->items['A']['project']->id, 'code' => 'SUM-ONE', 'name' => 'Resumen Uno', 'is_active' => true]);
        $this->one->assignments()->attach($this->items['A']['assignment']);
        $this->two = SummaryIndicator::create(['proyecto_id' => $this->items['A']['project']->id, 'code' => 'SUM-TWO', 'name' => 'Resumen Dos', 'is_active' => true]);
        $this->two->assignments()->attach([$this->items['A']['assignment']->id, $this->items['EX']['assignment']->id]);
        $this->other = SummaryIndicator::create(['proyecto_id' => $this->items['B']['project']->id, 'code' => 'SUM-OTHER', 'name' => 'Resumen otro proyecto', 'is_active' => true]);
        $this->other->assignments()->attach($this->items['B']['assignment']);
        $this->privateSummary = SummaryIndicator::create(['proyecto_id' => $this->items['C']['project']->id, 'code' => 'PRIVATE-SUMMARY', 'name' => 'Resumen privado', 'is_active' => true]);
        $this->privateSummary->assignments()->attach($this->items['C']['assignment']);
        $this->empty = SummaryIndicator::create(['proyecto_id' => $this->items['A']['project']->id, 'code' => 'EMPTY', 'name' => 'Resumen vacío', 'is_active' => true]);
    }

    private function filters(array $ids): array
    {
        return ['summary_indicator_ids' => $ids, 'reported' => ''];
    }

    public function test_admin_manages_catalog_and_project_assignments_without_modifying_records(): void
    {
        $this->get(route('summary-indicators.index'))->assertOk()->assertSee('SUM-ONE');
        $this->get(route('summary-indicators.create'))->assertOk();
        $this->post(route('summary-indicators.store'), ['proyecto_id' => $this->items['A']['project']->id, 'code' => 'NEW', 'name' => 'Nuevo resumen', 'description' => 'Prueba', 'is_active' => '1'])->assertSessionHasNoErrors()->assertRedirect();
        $new = SummaryIndicator::where('code', 'NEW')->firstOrFail();
        $this->get(route('summary-indicators.edit', $new))->assertOk();
        $this->put(route('summary-indicators.update', $new), ['proyecto_id' => $this->items['A']['project']->id, 'code' => 'NEW', 'name' => 'Nombre actualizado', 'is_active' => '1'])->assertSessionHasNoErrors();
        $this->get(route('summary-indicators.assignments', ['summary_indicator_id' => $this->one->id, 'proyecto_id' => $this->items['A']['project']->id]))->assertOk()->assertSee('SHARED')->assertSee('IND-EX')->assertDontSee('IND-B');
        $payload = ['summary_indicator_id' => $this->one->id, 'proyecto_id' => $this->items['A']['project']->id, 'assignment_ids' => [(string) $this->items['EX']['assignment']->id]];
        $this->put(route('summary-indicators.save-assignments'), $payload)->assertSessionHasNoErrors();
        $this->assertSame([$this->items['EX']['assignment']->id], $this->one->assignments()->get()->modelKeys());
        $this->delete(route('summary-indicators.destroy', $this->one))->assertSessionHas('error');
        unset($payload['assignment_ids']);
        $this->put(route('summary-indicators.save-assignments'), $payload)->assertSessionHasNoErrors();
        $this->assertSame([], $this->one->assignments()->get()->modelKeys());
        $this->assertSame([$this->items['B']['assignment']->id], $this->other->assignments()->get()->modelKeys());
        $this->delete(route('summary-indicators.destroy', $this->one))->assertRedirect();
        $this->delete(route('summary-indicators.destroy', $new))->assertRedirect();
        $this->assertDatabaseCount('reports', 4);
        $this->assertDatabaseCount('beneficiaries', 10);
        $this->assertDatabaseCount('indicador_proyecto', 4);
        $this->assertDatabaseCount('indicadores', 3);
    }

    public function test_assignment_validation_rejects_foreign_project_duplicates_and_non_admins(): void
    {
        $payload = ['summary_indicator_id' => $this->one->id, 'proyecto_id' => $this->items['A']['project']->id];
        foreach ([[$this->items['B']['assignment']->id], [999999], [$this->items['A']['assignment']->id, $this->items['A']['assignment']->id]] as $ids) {
            $this->putJson(route('summary-indicators.save-assignments'), $payload + ['assignment_ids' => $ids])->assertUnprocessable();
        }
        $this->putJson(route('summary-indicators.save-assignments'), ['summary_indicator_id' => $this->one->id,
            'proyecto_id' => $this->items['B']['project']->id, 'assignment_ids' => [$this->items['B']['assignment']->id]])->assertUnprocessable();
        $this->putJson(route('summary-indicators.save-assignments'), ['summary_indicator_id' => $this->one->id,
            'assignment_ids' => [$this->items['B']['assignment']->id]])->assertUnprocessable();
        $this->getJson(route('summary-indicators.assignments', ['summary_indicator_id' => $this->one->id, 'proyecto_id' => $this->items['B']['project']->id]))->assertUnprocessable();
        $this->assertCount(1, $this->one->assignments);
        $this->actingAs(User::factory()->create(['role' => 'reporter']));
        $this->get(route('summary-indicators.index'))->assertForbidden();
        $this->get(route('summary-indicators.assignments'))->assertForbidden();
        $this->postJson(route('summary-indicators.store'), ['code' => 'X', 'name' => 'X', 'is_active' => 1])->assertForbidden();
        $this->putJson(route('summary-indicators.save-assignments'), $payload)->assertForbidden();
        $this->delete(route('summary-indicators.destroy', $this->empty))->assertForbidden();
    }

    public function test_all_reports_filter_summary_union_without_duplicates_and_preserve_beneficiary_exclusions(): void
    {
        foreach ([[[$this->one->id, $this->other->id], 5, 5], [[$this->one->id, $this->two->id, $this->other->id], 5, 6], [[$this->empty->id], 0, 0], [[], 9, 10]] as [$ids, $beneficiaryCount, $generalCount]) {
            $filters = $this->filters($ids);
            $this->get(route('beneficiaries.summary', $filters))->assertOk()->assertViewHas('summary', fn ($summary) => $summary['total'] === $beneficiaryCount);
            foreach (['general-reports.index', 'indicator-reports.index'] as $route) {
                $this->get(route($route, $filters))->assertOk()->assertViewHas('summary', fn ($summary) => $summary['beneficiaries'] === $generalCount);
            }
            $this->get(route('servicios-programados.index', $filters + ['vista' => 'beneficiario']))->assertOk()->assertViewHas('entregas', fn ($rows) => $rows->count() === $generalCount);
            $this->getJson(route('reports.index', $filters + ['draw' => 1]))->assertOk()->assertJsonPath('recordsFiltered', $generalCount);
        }
        $this->get(route('beneficiaries.summary', $this->filters([$this->one->id, $this->other->id]) + ['indicator_filter' => ['project:'.$this->items['B']['assignment']->id]]))
            ->assertOk()->assertViewHas('summary', fn ($summary) => $summary['total'] === 3);
        $this->get(route('general-reports.index', $this->filters([$this->one->id]) + ['indicador_id' => [$this->items['A']['indicator']->id]]))
            ->assertOk()->assertViewHas('summary', fn ($summary) => $summary['beneficiaries'] === 2);
        $this->get(route('servicios-programados.index', $this->filters([$this->one->id]) + ['proyecto_id' => $this->items['A']['project']->id]))
            ->assertOk()->assertViewHas('entregas', fn ($rows) => $rows->count() === 2);
    }

    public function test_empty_unknown_inactive_and_malformed_summary_filters_never_fall_back_to_all_records(): void
    {
        $this->two->update(['is_active' => false]);
        foreach ([[$this->two->id], [99999], [$this->one->id, $this->one->id], [['bad']], 'bad'] as $ids) {
            foreach (['beneficiaries.summary', 'beneficiaries.export', 'general-reports.index', 'indicator-reports.export', 'servicios-programados.index', 'servicios-programados.export', 'reports.index', 'reports.export'] as $route) {
                $response = $this->getJson(route($route, $this->filters((array) $ids)));
                $this->assertSame(422, $response->status(), $route.' '.json_encode($ids));
            }
        }
        $this->assertDatabaseCount('reports', 4);
    }

    public function test_report_visibility_is_never_widened_by_summary_membership_or_guessed_ids(): void
    {
        // An invalid historical pivot must not advertise a different project's summary.
        $this->privateSummary->assignments()->attach($this->items['A']['assignment']);
        $user = User::factory()->create(['role' => 'reporter']);
        $user->givePermissionTo(['ver informes por servicios', 'exportar informes por servicios excel', 'exportar registros excel']);
        $this->items['A']['report']->update(['user_id' => $user->id]);
        $this->actingAs($user);
        foreach (['general-reports.index', 'indicator-reports.index'] as $route) {
            $this->get(route($route, $this->filters([$this->one->id])))->assertOk()->assertDontSee('PRIVATE-SUMMARY')->assertViewHas('summary', fn ($summary) => $summary['beneficiaries'] === 2);
        }
        $this->get(route('beneficiaries.summary', $this->filters([$this->one->id])))->assertOk()->assertViewHas('summary', fn ($summary) => $summary['total'] === 2);
        $this->get(route('servicios-programados.index', $this->filters([$this->one->id])))->assertOk()->assertDontSee('PRIVADO B')->assertViewHas('entregas', fn ($rows) => $rows->count() === 2);
        $this->get(route('reports.index', $this->filters([$this->one->id])))->assertOk()->assertViewHas('reports', fn ($rows) => $rows->modelKeys() === [$this->items['A']['report']->id]);
        foreach (['general-reports.index', 'beneficiaries.export', 'servicios-programados.export', 'reports.index'] as $route) {
            $this->getJson(route($route, $this->filters([$this->privateSummary->id])))->assertUnprocessable();
        }
    }

    public function test_exports_match_summary_filter_and_keep_existing_permissions_and_periods(): void
    {
        $filters = $this->filters([$this->one->id, $this->two->id, $this->other->id]);
        $book = $this->workbook($this->get(route('beneficiaries.export', $filters))->assertOk());
        try { $this->assertSame(6, $book->getActiveSheet()->getHighestDataRow()); } finally { $book->disconnectWorksheets(); }
        foreach (['general-reports.export', 'indicator-reports.export'] as $route) {
            $book = $this->workbook($this->get(route($route, $filters))->assertOk());
            try {
                $this->assertSame(6, $book->getActiveSheet()->getCell('B3')->getValue());
                $this->assertStringContainsString('SUM-ONE', json_encode($book->getActiveSheet()->toArray()));
            } finally { $book->disconnectWorksheets(); }
        }
        foreach (['beneficiario' => 6, 'servicio' => 6, 'resumen' => 3, 'agrupado' => 1] as $mode => $count) {
            $book = $this->workbook($this->get(route('servicios-programados.export', $filters + ['vista' => $mode]))->assertOk());
            try {
                $this->assertSame(3 + $count, $book->getActiveSheet()->getHighestDataRow());
                $this->assertStringContainsString('SUM-ONE', json_encode($book->getSheetByName('Filtros')->toArray()));
            } finally { $book->disconnectWorksheets(); }
        }
        $csv = $this->get(route('reports.export', $this->filters([$this->one->id, $this->other->id])))->assertOk()->streamedContent();
        $this->assertStringContainsString('PRIVADO A', $csv);
        $this->assertStringContainsString('PRIVADO B', $csv);
        $this->assertStringNotContainsString('PRIVADO C', $csv);
        $this->assertStringNotContainsString('PRIVADO EX', $csv);
        $export = $this->getJson(route('reports.index', $this->filters([$this->one->id, $this->other->id]) + ['draw' => 1, 'export_type' => 'excel']))->assertOk();
        $this->assertCount(5, json_decode($export->streamedContent(), true, 512, JSON_THROW_ON_ERROR)['data']);
        $this->get(route('general-reports.index', $this->filters([$this->one->id]) + ['reporting_period' => ['2026-11']]))
            ->assertOk()->assertViewHas('summary', fn ($summary) => $summary['beneficiaries'] === 0);
    }

    public function test_new_record_form_does_not_offer_summaries_and_configuration_never_creates_records(): void
    {
        $page = $this->get(route('reports.create'))->assertOk()->assertDontSee('name="summary_indicator_ids[]"', false)->assertDontSee('SUM-ONE');
        $options = $page->viewData('projectIndicatorOptions');
        $this->assertCount(2, $options[$this->items['A']['project']->id]);
        $this->assertDatabaseCount('reports', 4);
        $this->assertDatabaseCount('beneficiaries', 10);
        $this->assertDatabaseCount('report_indicator_copies', 0);
    }

    public function test_summary_filter_keeps_reported_updates_inside_the_selected_records(): void
    {
        $this->admin->update(['can_mark_reported' => true]);
        $this->post(route('beneficiaries.mark-reported'), $this->filters([$this->one->id]) + ['reported_at' => today()->toDateString()])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(2, $this->items['A']['report']->beneficiaries()->whereNotNull('reported_at')->count());
        $this->assertSame(0, $this->items['B']['report']->beneficiaries()->whereNotNull('reported_at')->count());
        $this->assertSame(0, $this->items['C']['report']->beneficiaries()->whereNotNull('reported_at')->count());
        $this->assertSame(0, $this->items['EX']['report']->beneficiaries()->whereNotNull('reported_at')->count());
    }

    public function test_inactive_project_indicators_remain_queryable_for_historical_records(): void
    {
        $this->items['A']['assignment']->update(['estatus' => false]);
        $this->get(route('beneficiaries.summary', $this->filters([$this->one->id])))->assertOk()
            ->assertViewHas('summary', fn ($summary) => $summary['total'] === 2);
        $this->get(route('general-reports.index', $this->filters([$this->one->id])))->assertOk()
            ->assertViewHas('summary', fn ($summary) => $summary['beneficiaries'] === 2);
        $this->get(route('summary-indicators.assignments', ['summary_indicator_id' => $this->one->id, 'proyecto_id' => $this->items['A']['project']->id]))
            ->assertOk()->assertSee('Inactivo (histórico)');
    }

    public function test_project_is_required_and_assignment_screen_uses_only_its_owner(): void
    {
        foreach ([null, '', 999999] as $projectId) {
            $this->postJson(route('summary-indicators.store'), ['proyecto_id' => $projectId, 'code' => 'REQUIRED', 'name' => 'Prueba', 'is_active' => 1])
                ->assertUnprocessable()->assertJsonValidationErrors('proyecto_id');
        }
        $this->get(route('summary-indicators.assignments', ['summary_indicator_id' => $this->one->id]))
            ->assertOk()->assertDontSee('<select name="proyecto_id"', false)
            ->assertViewHas('project', fn ($project) => $project->id === $this->items['A']['project']->id)
            ->assertViewHas('assignments', fn ($rows) => $rows->pluck('proyecto_id')->unique()->all() === [$this->items['A']['project']->id]);
        $this->get(route('beneficiaries.summary', $this->filters([$this->one->id])))
            ->assertOk()->assertSee('PROY-A / SUM-ONE');
    }

    public function test_changing_project_requires_explicit_reset_of_only_that_summary_memberships(): void
    {
        $payload = ['proyecto_id' => $this->items['B']['project']->id, 'code' => $this->one->code, 'name' => $this->one->name, 'is_active' => 1];
        $this->putJson(route('summary-indicators.update', $this->one), $payload)->assertUnprocessable()->assertJsonValidationErrors('proyecto_id');
        $this->assertSame($this->items['A']['project']->id, $this->one->fresh()->proyecto_id);
        $this->assertCount(1, $this->one->assignments()->get());
        $this->putJson(route('summary-indicators.update', $this->one), $payload + ['reset_assignments_on_project_change' => 1])->assertRedirect();
        $this->assertSame($this->items['B']['project']->id, $this->one->fresh()->proyecto_id);
        $this->assertCount(0, $this->one->assignments()->get());
        $this->assertCount(2, $this->two->assignments()->get());
        $this->assertCount(1, $this->other->assignments()->get());
        $this->assertDatabaseCount('reports', 4);
        $this->assertDatabaseCount('beneficiaries', 10);
        $this->assertDatabaseCount('indicador_proyecto', 4);
        $this->putJson(route('summary-indicators.save-assignments'), ['summary_indicator_id' => $this->one->id,
            'assignment_ids' => [$this->items['B']['assignment']->id]])->assertRedirect();
    }

    public function test_pending_legacy_summaries_require_explicit_project_confirmation(): void
    {
        $this->one->update(['proyecto_id' => null]);
        $this->get(route('summary-indicators.index'))->assertOk()->assertSee('Pendiente: seleccione proyecto en Editar');
        $this->get(route('summary-indicators.assignments', ['summary_indicator_id' => $this->one->id]))
            ->assertOk()->assertSee('Asignar proyecto en Editar')->assertViewHas('assignments', fn ($rows) => $rows->isEmpty());
        $this->getJson(route('general-reports.index', $this->filters([$this->one->id])))->assertUnprocessable();
        $this->putJson(route('summary-indicators.save-assignments'), ['summary_indicator_id' => $this->one->id,
            'proyecto_id' => $this->items['A']['project']->id, 'assignment_ids' => [$this->items['A']['assignment']->id]])->assertUnprocessable();
        $payload = ['proyecto_id' => $this->items['B']['project']->id, 'code' => $this->one->code, 'name' => $this->one->name, 'is_active' => 1];
        $this->putJson(route('summary-indicators.update', $this->one), $payload)->assertUnprocessable();
        $payload['proyecto_id'] = $this->items['A']['project']->id;
        $this->putJson(route('summary-indicators.update', $this->one), $payload)->assertRedirect();
        $this->assertSame($this->items['A']['project']->id, $this->one->fresh()->proyecto_id);
        $this->assertSame([$this->items['A']['assignment']->id], $this->one->assignments()->get()->modelKeys());
    }

    public function test_corrupt_foreign_memberships_cannot_leak_records_in_reports_or_exports(): void
    {
        $this->one->assignments()->attach([$this->items['B']['assignment']->id, $this->items['C']['assignment']->id]);
        $filters = $this->filters([$this->one->id]);
        $this->get(route('beneficiaries.summary', $filters))->assertOk()->assertViewHas('summary', fn ($summary) => $summary['total'] === 2);
        foreach (['general-reports.index', 'indicator-reports.index'] as $route) {
            $this->get(route($route, $filters))->assertOk()->assertViewHas('summary', fn ($summary) => $summary['beneficiaries'] === 2);
        }
        $this->getJson(route('reports.index', $filters + ['draw' => 1]))->assertOk()->assertJsonPath('recordsFiltered', 2);
        $this->get(route('servicios-programados.index', $filters + ['vista' => 'beneficiario']))
            ->assertOk()->assertViewHas('entregas', fn ($rows) => $rows->count() === 2);
        foreach (['beneficiaries.export', 'general-reports.export', 'indicator-reports.export', 'servicios-programados.export'] as $route) {
            $book = $this->workbook($this->get(route($route, $filters + ['vista' => 'beneficiario']))->assertOk());
            try {
                $content = json_encode($book->getActiveSheet()->toArray());
                $this->assertStringNotContainsString('PRIVADO B', $content);
                $this->assertStringNotContainsString('PRIVADO C', $content);
                if (in_array($route, ['general-reports.export', 'indicator-reports.export'])) {
                    $this->assertSame(2, $book->getActiveSheet()->getCell('B3')->getValue());
                }
            } finally { $book->disconnectWorksheets(); }
        }
        $csv = $this->get(route('reports.export', $filters))->assertOk()->streamedContent();
        $this->assertStringNotContainsString('PRIVADO B', $csv);
        $this->assertStringNotContainsString('PRIVADO C', $csv);
    }

    public function test_ownership_migration_preserves_legacy_data_without_inferring_projects(): void
    {
        // Runs only inside the guarded SQLite :memory: test database.
        $migration = require database_path('migrations/2026_10_26_150000_scope_summary_indicators_to_projects.php');
        $members = DB::table('summary_indicator_assignments')->orderBy('summary_indicator_id')->orderBy('indicador_proyecto_id')->get()->toArray();
        $count = SummaryIndicator::count();
        $migration->down();
        $this->assertEquals($members, DB::table('summary_indicator_assignments')->orderBy('summary_indicator_id')->orderBy('indicador_proyecto_id')->get()->toArray());
        $migration->up();
        $this->assertSame($count, SummaryIndicator::count());
        $this->assertSame(0, SummaryIndicator::whereNotNull('proyecto_id')->count());
        $this->assertEquals($members, DB::table('summary_indicator_assignments')->orderBy('summary_indicator_id')->orderBy('indicador_proyecto_id')->get()->toArray());
        $this->assertDatabaseCount('reports', 4);
        $this->assertDatabaseCount('beneficiaries', 10);
    }

    private function workbook(TestResponse $response)
    {
        $path = tempnam(sys_get_temp_dir(), 'sia-summary-filter-');
        try {
            file_put_contents($path, $response->streamedContent());
            return IOFactory::load($path);
        } finally { unlink($path); }
    }
}
