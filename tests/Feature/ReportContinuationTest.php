<?php

namespace Tests\Feature;

use App\Models\{AuditLog, Beneficiary, Indicador, IndicadorProyecto, Municipality, Parish, PlaceName, Proyecto, Report, ReportingPeriod, Sector, State, SystemSetting, User, UserGroup};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ReportContinuationTest extends TestCase
{
    use RefreshDatabase;
    use \Tests\Concerns\CreatesReportProjectContext;

    private User $owner;
    private Report $report;
    private array $headers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create(['role' => 'reporter']);
        $this->owner->syncRoles([]);
        $this->owner->givePermissionTo(['registrar actividad', 'editar registros', 'ver detalle de registros', 'solo ver registros']);
        $state = State::create(['code' => 'C', 'name' => 'Estado']);
        $municipality = Municipality::create(['state_id' => $state->id, 'code' => 'C1', 'name' => 'Municipio']);
        $parish = Parish::create(['municipality_id' => $municipality->id, 'code' => 'C11', 'name' => 'Parroquia']);
        $sector = Sector::create(['name' => 'Sector', 'slug' => 'continuation']);
        PlaceName::create(['name' => 'Lugar de prueba']);
        $this->headers = $this->reportProjectContext($this->owner, $state, $sector) + [
            'report_date' => today()->toDateString(), 'organization' => 'ASONACOP', 'state_id' => $state->id,
            'municipality_id' => $municipality->id, 'parish_id' => $parish->id,
            'installation_type' => 'Comunidad / Espacio Comunitario', 'place_name' => 'Lugar de prueba',
        ];
        $this->actingAs($this->owner);
        $result = $this->postJson(route('beneficiaries.store'), $this->headers + ['beneficiary' => $this->person()])->assertCreated();
        $this->report = Report::findOrFail($result->json('report.id'));
    }

    private function person(array $changes = []): array
    {
        return array_replace(['has_informed_consent' => true, 'full_name' => 'Persona de prueba', 'age' => 30,
            'sex' => 'Mujer', 'national_id' => 'V12345678', 'disability' => 'Ninguna', 'ethnicity' => 'Ninguna',
            'pregnant_lactating' => 'Ninguna'], $changes);
    }

    private function append(array $changes = [])
    {
        return $this->postJson(route('reports.continue-beneficiary', $this->report), ['beneficiary' => $this->person($changes)]);
    }

    public function test_registrar_without_edit_permission_cannot_see_or_use_continuation_but_can_create_new_records(): void
    {
        $this->owner->revokePermissionTo('editar registros');
        $this->actingAs($this->owner->fresh());
        $this->get(route('reports.show', $this->report))->assertOk()->assertViewHas('canContinueReport', false)
            ->assertDontSee('href="'.route('reports.resume', $this->report).'"', false);
        $this->get(route('reports.resume', $this->report))->assertForbidden();
        $this->append()->assertForbidden();
        $this->postJson(route('reports.continuation-recurrence', $this->report), $this->person())->assertForbidden();
        $this->postJson(route('reports.continuation-lookup', $this->report), ['national_id' => 'V12345678'])->assertForbidden();
        $this->postJson(route('beneficiaries.store'), $this->headers + ['beneficiary' => $this->person()])
            ->assertCreated()->assertJsonPath('report.resume_url', null);
        $this->assertSame(1, $this->report->beneficiaries()->count());
        $this->assertSame(2, Report::count());
    }

    public function test_editor_without_registration_permission_can_continue_with_lookup_and_recurrence_without_gaining_create_access(): void
    {
        $this->owner->revokePermissionTo('registrar actividad');
        $this->actingAs($this->owner->fresh());
        $this->get(route('reports.resume', $this->report))->assertOk()->assertSee('data-lookup-enabled="1"', false)
            ->assertSee(route('reports.continuation-lookup', $this->report), false)
            ->assertSee(json_encode(route('reports.continuation-recurrence', $this->report)), false);
        $this->postJson(route('reports.continuation-recurrence', $this->report), $this->person() + ['proyecto_id' => 999999])
            ->assertOk()->assertJsonPath('comparable', true)->assertJsonPath('is_recurrent', true)
            ->assertHeader('Cache-Control', 'no-store, private')->assertDontSee('PERSONA DE PRUEBA');
        $this->postJson(route('reports.continuation-lookup', $this->report), ['national_id' => 'V12345678'])
            ->assertOk()->assertJsonPath('matched', true)->assertHeader('Cache-Control', 'no-store, private');
        foreach (['reports.continuation-recurrence', 'reports.continuation-lookup'] as $route) {
            $this->assertNull(AuditLog::where('route_name', $route)->latest('id')->firstOrFail()->request_payload);
        }
        $this->append()->assertOk()->assertJsonPath('report.id', $this->report->id)->assertJsonPath('summary.total', 2);
        $this->get(route('reports.create'))->assertForbidden();
        $this->postJson(route('beneficiaries.store'), $this->headers + ['beneficiary' => $this->person()])->assertForbidden();
        $this->postJson(route('beneficiaries.lookup'), ['national_id' => 'V12345678'])->assertForbidden();
        $this->postJson(route('beneficiaries.project-recurrence'), $this->person() + ['proyecto_id' => $this->report->proyecto_id])->assertForbidden();
        $this->assertSame(1, Report::count());
    }

    public function test_continuation_cannot_be_used_after_edit_permission_is_revoked_even_by_an_administrator(): void
    {
        $this->get(route('reports.resume', $this->report))->assertOk();
        $this->owner->revokePermissionTo('editar registros');
        $this->actingAs($this->owner->fresh());
        $this->append()->assertForbidden();
        $this->postJson(route('reports.continuation-recurrence', $this->report), $this->person())->assertForbidden();
        $this->postJson(route('reports.continuation-lookup', $this->report), ['national_id' => 'V12345678'])->assertForbidden();
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->syncRoles([]);
        $admin->givePermissionTo(['registrar actividad', 'ver detalle de registros']);
        $this->actingAs($admin);
        $this->get(route('reports.show', $this->report))->assertOk()->assertViewHas('canContinueReport', false);
        $this->get(route('reports.resume', $this->report))->assertForbidden();
        $this->append()->assertForbidden();
        $this->assertSame(1, $this->report->beneficiaries()->count());
    }

    public function test_continuation_lookups_respect_record_authorization_and_existing_profile_visibility(): void
    {
        $outsider = User::factory()->create(['role' => 'reporter']);
        $hiddenReport = $this->report->replicate();
        $hiddenReport->user_id = $outsider->id;
        $hiddenReport->save();
        $hiddenReport->beneficiaries()->create($this->person(['full_name' => 'PERFIL RESERVADO', 'national_id' => 'V99999999']));
        $this->postJson(route('reports.continuation-lookup', $this->report), ['national_id' => 'V99999999'])
            ->assertOk()->assertJsonPath('matched', false)->assertDontSee('PERFIL RESERVADO');
        $outsider->givePermissionTo(['editar registros', 'ver detalle de registros']);
        $outsider->projects()->attach($this->report->proyecto_id);
        $this->actingAs($outsider);
        $this->postJson(route('reports.continuation-lookup', $this->report), ['national_id' => 'V12345678'])->assertForbidden();
        $this->postJson(route('reports.continuation-recurrence', $this->report), $this->person())->assertForbidden();
    }

    public function test_owner_with_edit_permission_can_resume_and_form_only_allows_adding_people(): void
    {
        $this->assertTrue($this->owner->can('editar registros'));
        $page = $this->get(route('reports.resume', $this->report))->assertOk()
            ->assertViewHas('continuing', true)->assertSee('Continuar registro #'.$this->report->id)
            ->assertSee('data-continuing="1"', false)->assertSee('data-lookup-enabled="1"', false)
            ->assertSee(route('reports.continue-beneficiary', $this->report), false)
            ->assertDontSee('data-report-update-url=', false)->assertDontSee('id="save-report-changes"', false)
            ->assertDontSee('actionGroup.append(edit)')->assertDontSee('actionGroup.append(remove)');
        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$page->getContent());
        $xpath = new \DOMXPath($dom);
        $this->assertSame(1, $xpath->query('//fieldset[@class="report-continuation-context"][@disabled]')->length);
        $this->assertSame(0, $xpath->query('//fieldset[@class="beneficiary-entry"][@disabled]')->length);
        $this->assertSame(0, $xpath->query('//fieldset[contains(@class,"report-continuation-context")]//fieldset[@class="beneficiary-entry"]')->length);
        $this->assertSame(0, $xpath->query('//div[@class="beneficiary-list-card"][@hidden]')->length);
        $this->assertSame(1, Report::count());
    }

    public function test_resume_after_a_new_login_keeps_same_list_period_headers_and_saved_people(): void
    {
        $this->report->update(['reporting_period' => '2026-01', 'activity_details' => 'Detalles originales']);
        SystemSetting::updateOrCreate(['key' => SystemSetting::CURRENT_PERIOD], ['value' => '2026-02']);
        ReportingPeriod::create(['period' => '2026-02', 'is_closed' => true]);
        $this->app['auth']->forgetGuards();
        $this->actingAs($this->owner->fresh());
        $this->get(route('reports.resume', $this->report))->assertOk()->assertViewHas('reportingPeriod', '2026-01');
        $this->append()->assertOk()->assertJsonPath('report.id', $this->report->id)
            ->assertJsonPath('summary.total', 2)->assertJsonPath('beneficiary.is_recurrent', true);
        $this->assertSame(1, Report::count());
        $this->assertSame(2, $this->report->beneficiaries()->count());
        $this->assertSame('2026-01', $this->report->fresh()->reporting_period);
        $this->assertSame('Detalles originales', $this->report->fresh()->activity_details);
        $this->get(route('reports.resume', $this->report))->assertOk()
            ->assertViewHas('initialSummary', fn ($summary) => $summary['total'] === 2);
    }

    public function test_headers_owner_report_id_and_associated_selection_cannot_be_changed_by_client(): void
    {
        $before = $this->report->getAttributes();
        $this->postJson(route('reports.continue-beneficiary', $this->report), [
            'beneficiary' => $this->person(['national_id' => 'V99999999']), 'report_id' => 999999,
            'proyecto_id' => 999999, 'indicador_proyecto_id' => 999999, 'report_date' => '2000-01-01',
            'reporting_period' => '2000-01', 'period_snapshot' => '2000-01', 'user_id' => 999999,
            'place_name' => 'Otro lugar', 'reporter_first_name' => 'Falso', 'reporter_email' => 'fake@example.test',
            'activity_details' => 'Cambio no autorizado', 'associated_indicator_ids' => [999999],
        ])->assertOk()->assertJsonPath('report.id', $this->report->id)->assertJsonPath('summary.total', 2);
        $after = $this->report->fresh();
        foreach (['user_id', 'proyecto_id', 'indicador_proyecto_id', 'report_date', 'reporting_period', 'place_name',
            'reporter_first_name', 'reporter_email', 'activity_details'] as $field) {
            $this->assertSame($before[$field], $after->getRawOriginal($field));
        }
        $this->assertSame(1, Report::count());
    }

    public function test_reviewed_and_closed_lists_are_read_only_even_if_page_was_open_before_change(): void
    {
        $this->report->update(['status' => 'reviewed']);
        $this->get(route('reports.resume', $this->report))->assertConflict();
        $this->append()->assertConflict();
        $this->postJson(route('reports.continuation-recurrence', $this->report), $this->person())->assertConflict();
        $this->postJson(route('reports.continuation-lookup', $this->report), ['national_id' => 'V12345678'])->assertConflict();
        $this->get(route('reports.show', $this->report))->assertOk()->assertViewHas('canContinueReport', false);
        $this->report->update(['status' => 'submitted', 'reporting_period' => '2026-01']);
        ReportingPeriod::create(['period' => '2026-01', 'is_closed' => true]);
        $this->get(route('reports.resume', $this->report))->assertConflict();
        $this->append()->assertConflict();
        $this->assertSame(1, Beneficiary::count());
    }

    public function test_permission_project_assignment_and_group_scope_are_enforced_on_get_and_post(): void
    {
        $outsider = User::factory()->create(['role' => 'reporter']);
        $outsider->givePermissionTo(['registrar actividad', 'editar registros', 'ver detalle de registros']);
        $outsider->projects()->attach($this->report->proyecto_id);
        $this->actingAs($outsider);
        $this->get(route('reports.resume', $this->report))->assertForbidden();
        $this->append()->assertForbidden();
        $this->owner->revokePermissionTo('editar registros');
        $this->actingAs($this->owner->fresh());
        $this->get(route('reports.resume', $this->report))->assertForbidden();
        $this->append()->assertForbidden();
        $this->owner->givePermissionTo('editar registros');
        $this->owner->revokePermissionTo('ver detalle de registros');
        $this->actingAs($this->owner->fresh());
        $this->get(route('reports.resume', $this->report))->assertForbidden();
        $this->append()->assertForbidden();
        $this->postJson(route('reports.continuation-recurrence', $this->report), $this->person())->assertForbidden();
        $this->postJson(route('reports.continuation-lookup', $this->report), ['national_id' => 'V12345678'])->assertForbidden();
        $this->owner->givePermissionTo('ver detalle de registros');
        $this->owner->projects()->detach();
        $this->actingAs($this->owner->fresh());
        $this->get(route('reports.resume', $this->report))->assertConflict();
        $this->append()->assertConflict();
        $this->assertSame(1, Beneficiary::count());
    }

    public function test_list_offers_only_view_and_continuation_remains_in_detail_when_allowed(): void
    {
        $url = route('reports.resume', $this->report);
        $this->get(route('reports.index'))->assertOk()->assertDontSee($url, false)
            ->assertDontSee('Continuar agregando beneficiarios')
            ->assertSee('href="'.route('reports.show', $this->report).'"', false);
        $this->get(route('reports.show', $this->report))->assertOk()->assertSee($url, false);
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->getJson(route('reports.index', ['draw' => 1]))->assertOk()
            ->assertJsonPath('data.0.actions', '<a href="'.route('reports.show', $this->report).'">Ver</a>');
        $this->get(route('reports.show', $this->report))->assertOk()->assertSee($url, false);
        $this->report->update(['status' => 'reviewed']);
        $this->getJson(route('reports.index', ['draw' => 1]))->assertOk()
            ->assertJsonPath('data.0.actions', fn ($actions) => !str_contains($actions, $url));
        $this->actingAs($this->owner)->get(route('reports.index'))->assertOk()->assertDontSee($url, false);
        $this->get(route('reports.show', $this->report))->assertOk()->assertDontSee($url, false);
    }

    public function test_detail_actions_are_grouped_below_summary_with_status_outside_the_toolbar(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $response = $this->actingAs($admin)->get(route('reports.show', $this->report))->assertOk();
        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        $xpath = new \DOMXPath($dom);
        $toolbar = $xpath->query('//nav[@class="report-detail-actions"]')->item(0);
        $this->assertNotNull($toolbar);
        $this->assertSame('Acciones del registro', $toolbar->getAttribute('aria-label'));
        $this->assertSame(1, $xpath->query('//div[@class="report-detail-meta"]/span[contains(@class,"status")]')->length);
        $this->assertSame(0, $xpath->query('.//span[contains(@class,"status")]', $toolbar)->length);
        $labels = [];
        foreach ($xpath->query('.//a | .//button', $toolbar) as $button) {
            $labels[] = trim($button->textContent);
            $this->assertSame(1, $xpath->query('./i[@aria-hidden="true"]', $button)->length);
        }
        $this->assertSame(['Continuar agregando beneficiarios', 'Editar registro', 'Eliminar registro', 'Volver a registros'], $labels);
        $this->assertSame(1, $xpath->query('.//form[@class="report-delete-form"]/input[@name="_method"][@value="DELETE"]', $toolbar)->length);
        $this->report->update(['reporting_period' => '2026-01']);
        ReportingPeriod::create(['period' => '2026-01', 'is_closed' => true]);
        $this->get(route('reports.show', $this->report))->assertOk()
            ->assertSee('report-detail-back')->assertDontSee('class="report-detail-main-actions"', false)
            ->assertDontSee('class="report-delete-form"', false);
    }

    public function test_validation_and_historical_location_snapshot_are_preserved(): void
    {
        PlaceName::where('name', $this->report->place_name)->delete();
        $this->append(['full_name' => ''])->assertUnprocessable()->assertJsonValidationErrors('beneficiary.full_name');
        $this->append(['age' => 12])->assertOk()->assertJsonPath('summary.total', 2);
        $this->append(['age' => 12, 'national_id' => null, 'caregiver_national_id' => '999999',
            'caregiver_full_name' => 'Cuidador de prueba', 'caregiver_relationship' => 'Madre'])
            ->assertOk()->assertJsonPath('summary.total', 3);
        $this->assertSame(1, Report::count());
    }

    public function test_continuation_preserves_derived_legacy_sector_and_services_and_cannot_upload_evidence(): void
    {
        $assignment = IndicadorProyecto::findOrFail($this->report->indicador_proyecto_id);
        $activity = \App\Models\Actividad::create(['codigo' => 'CONT-ACT', 'descripcion' => 'Actividad de prueba', 'estatus' => true]);
        $projectActivity = $assignment->asignacionesActividades()->create(['actividad_id' => $activity->id, 'estatus' => true]);
        $service = \App\Models\Servicio::create(['nombre' => 'Servicio de prueba', 'estatus' => true]);
        $assignedService = $projectActivity->asignacionesServicios()->create(['servicio_id' => $service->id, 'estatus' => true]);
        $this->report->update(['sector_id' => $assignment->asignacionSector->sector_id,
            'actividad_indicador_id' => $projectActivity->id]);
        $this->report->serviciosActividad()->attach($assignedService);
        $this->append()->assertOk()->assertJsonPath('summary.total', 2);
        $this->assertSame($assignment->asignacionSector->sector_id, $this->report->fresh()->sector_id);
        $this->assertSame([$assignedService->id], $this->report->serviciosActividad()->pluck('servicio_actividad.id')->all());
        $this->postJson(route('reports.continue-beneficiary', $this->report), ['beneficiary' => $this->person(),
            'evidence_1' => UploadedFile::fake()->create('evidencia.pdf', 1)])
            ->assertUnprocessable()->assertJsonValidationErrors('evidence_1');
        $this->assertSame(0, $this->report->evidences()->count());
        $this->assertSame(2, $this->report->beneficiaries()->count());
    }

    public function test_continuation_retains_direct_associated_copies_and_rolls_back_when_one_is_read_only(): void
    {
        $indicator = Indicador::create(['codigo' => 'CONT-ASSOCIATED', 'descripcion' => 'Asociado',
            'unidad_conteo' => 'Personas', 'espacio_coordinacion' => 'NNA', 'edad_desde' => 0, 'edad_hasta' => 120]);
        $assignment = IndicadorProyecto::create(['proyecto_id' => $this->headers['proyecto_id'],
            'sector_proyecto_id' => $this->headers['sector_proyecto_id'], 'indicador_id' => $indicator->id, 'estatus' => true]);
        IndicadorProyecto::findOrFail($this->headers['indicador_proyecto_id'])->indicadoresAsociados()->attach($assignment);
        $result = $this->postJson(route('beneficiaries.store'), $this->headers + ['beneficiary' => $this->person(),
            'associated_indicator_ids' => [$assignment->id]])->assertCreated();
        $this->report = Report::findOrFail($result->json('report.id'));
        $copy = Report::findOrFail($result->json('associated_reports.0.id'));
        $this->append()->assertOk()->assertJsonCount(1, 'associated_reports');
        $this->assertSame(2, $copy->beneficiaries()->count());
        $this->assertSame(2, $this->report->beneficiaries()->count());
        $copy->update(['status' => 'reviewed']);
        $this->append()->assertConflict();
        $this->assertSame(2, $copy->beneficiaries()->count());
        $this->assertSame(2, $this->report->beneficiaries()->count());
    }

    public function test_coordinator_can_continue_only_an_editable_team_list_without_changing_original_registrant(): void
    {
        $group = UserGroup::create(['name' => 'Equipo', 'is_active' => true, 'allow_member_editing' => true]);
        $coordinator = User::factory()->create(['role' => 'coordinator']);
        $coordinator->givePermissionTo(['editar registros', 'ver detalle de registros']);
        $coordinator->projects()->attach($this->report->proyecto_id);
        $group->users()->attach([$this->owner->id, $coordinator->id]);
        $this->actingAs($coordinator);
        $this->get(route('reports.resume', $this->report))->assertOk();
        $this->append()->assertOk()->assertJsonPath('report.id', $this->report->id);
        $this->assertSame($this->owner->id, $this->report->fresh()->user_id);
        $this->assertSame($this->owner->email, $this->report->fresh()->reporter_email);
        $group->update(['allow_member_editing' => false]);
        $this->get(route('reports.resume', $this->report))->assertForbidden();
        $this->append()->assertForbidden();
        $this->assertSame(2, $this->report->beneficiaries()->count());
    }

    public function test_resume_ignores_stale_form_headers_and_all_form_modes_generate_valid_javascript(): void
    {
        $this->withSession(['_old_input' => ['proyecto_id' => 999999, 'indicador_proyecto_id' => 999999,
            'place_name' => 'Lugar de otro formulario', 'report_date' => '2000-01-01']]);
        $page = $this->get(route('reports.resume', $this->report))->assertOk();
        $page->assertViewHas('selectedProjectId', $this->report->proyecto_id)
            ->assertDontSee('const initialIndicator = 999999', false)->assertDontSee('value="2000-01-01"', false);
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);
        $pages = [$page, $this->get(route('reports.create'))->assertOk(),
            $this->get(route('reports.edit', $this->report))->assertOk()];
        foreach ($pages as $response) {
            $dom = new \DOMDocument();
            @$dom->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
            $xpath = new \DOMXPath($dom);
            foreach ($xpath->query('//script[not(@src)]') as $script) {
                $process = new Process(['node', '--check']);
                $process->setInput($script->textContent);
                $process->run();
                $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
            }
        }
    }

    public function test_unavailable_project_or_indicator_cannot_be_continued(): void
    {
        $project = Proyecto::findOrFail($this->report->proyecto_id);
        $project->update(['estatus' => false]);
        $this->get(route('reports.resume', $this->report))->assertConflict();
        $this->append()->assertConflict();
        $project->update(['estatus' => true]);
        IndicadorProyecto::findOrFail($this->report->indicador_proyecto_id)->update(['estatus' => false]);
        $this->get(route('reports.resume', $this->report))->assertConflict();
        $this->append()->assertConflict();
        $this->assertSame(1, Beneficiary::count());
    }
}
