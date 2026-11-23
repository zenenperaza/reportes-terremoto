<?php

namespace Tests\Feature;

use App\Models\{AuditLog, Beneficiary, Municipality, Parish, Report, ReportingPeriod, Sector, State, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReportDeletionAuditTest extends TestCase
{
    use RefreshDatabase;
    use \Tests\Concerns\CreatesReportProjectContext;

    private User $admin;
    private Report $report;
    private Beneficiary $person;
    private Beneficiary $other;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->admin = User::factory()->create(['role' => 'admin']);
        $state = State::create(['code' => 'D', 'name' => 'Estado de prueba']);
        $municipality = Municipality::create(['state_id' => $state->id, 'code' => 'D1', 'name' => 'Municipio']);
        $parish = Parish::create(['municipality_id' => $municipality->id, 'code' => 'D11', 'name' => 'Parroquia']);
        $sector = Sector::create(['name' => 'Sector', 'slug' => 'deletion-audit']);
        $context = $this->reportProjectContext($this->admin, $state, $sector);
        $this->report = Report::create($context + [
            'user_id' => $this->admin->id, 'reporting_period' => '2026-11', 'report_date' => '2026-11-10',
            'reporter_first_name' => 'Persona', 'reporter_last_name' => 'Registradora', 'reporter_email' => $this->admin->email,
            'organization' => 'ASONACOP', 'state_id' => $state->id, 'municipality_id' => $municipality->id,
            'parish_id' => $parish->id, 'sector_id' => $sector->id, 'installation_type' => 'Comunidad / Espacio Comunitario',
            'place_name' => 'Lugar de prueba', 'recurrence_status' => 'no_recurrente', 'total_beneficiaries' => 2,
            'beneficiary_breakdown' => [], 'status' => 'submitted',
        ]);
        $this->person = $this->report->beneficiaries()->create([
            'full_name' => 'Persona Eliminada', 'national_id' => 'V00123456', 'age' => 25, 'sex' => 'Mujer',
            'phone' => '04120000000', 'caregiver_full_name' => 'No conservar cuidador', 'is_recurrent' => false,
        ]);
        $this->other = $this->report->beneficiaries()->create([
            'full_name' => null, 'national_id' => null, 'age' => 15, 'sex' => 'Hombre', 'is_recurrent' => false,
        ]);
        $this->actingAs($this->admin);
    }

    public static function invalidReasons(): array
    {
        return ['missing' => [null], 'blank' => ['   '], 'array' => [['invalid']], 'numeric' => [123], 'too long' => [str_repeat('a', 1001)]];
    }

    #[DataProvider('invalidReasons')]
    public function test_both_endpoints_reject_invalid_reasons_without_deleting_anyone(mixed $reason): void
    {
        foreach ([route('beneficiaries.destroy', $this->person), route('reports.destroy', $this->report)] as $url) {
            $this->deleteJson($url, ['deletion_reason' => $reason])->assertUnprocessable()->assertJsonValidationErrors('deletion_reason');
            $this->assertDatabaseHas('beneficiaries', ['id' => $this->person->id]);
            $this->assertDatabaseHas('beneficiaries', ['id' => $this->other->id]);
            $this->assertDatabaseHas('reports', ['id' => $this->report->id]);
        }
        $this->assertSame(0, AuditLog::where('status_code', '<', 400)->where('method', 'DELETE')->count());
    }

    public function test_person_deletion_keeps_a_single_trusted_snapshot_and_reason(): void
    {
        $this->deleteJson(route('beneficiaries.destroy', $this->person), [
            'deletion_reason' => '  Error de carga  ',
            'deletion' => ['beneficiaries' => [['full_name' => 'Identidad falsa']]],
        ])->assertOk()->assertJsonPath('report_deleted', false);

        $this->assertDatabaseMissing('beneficiaries', ['id' => $this->person->id]);
        $this->assertDatabaseHas('beneficiaries', ['id' => $this->other->id]);
        $this->assertSame(1, $this->report->fresh()->total_beneficiaries);
        $logs = AuditLog::where('route_name', 'beneficiaries.destroy')->get();
        $this->assertCount(1, $logs);
        $log = $logs->first();
        $this->assertSame($this->admin->id, $log->user_id);
        $this->assertSame(200, $log->status_code);
        $deletion = $log->request_payload['deletion'];
        $this->assertSame('Error de carga', $deletion['reason']);
        $this->assertSame($this->report->id, $deletion['report_id']);
        $this->assertSame($this->report->proyecto->codigo, $deletion['project_code']);
        $this->assertSame('2026-11', $deletion['reporting_period']);
        $this->assertFalse($deletion['report_deleted']);
        $this->assertSame([['id' => $this->person->id, 'full_name' => 'PERSONA ELIMINADA', 'national_id' => 'V00123456']], $deletion['beneficiaries']);
        $this->assertStringNotContainsString('04120000000', json_encode($log->request_payload));
        $this->assertStringNotContainsString('cuidador', json_encode($log->request_payload));

        $this->getJson(route('audit-logs.show', $log))->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('eliminacion.beneficiaries.0.national_id', 'V00123456');
        $this->deleteJson(route('beneficiaries.destroy', $this->person), ['deletion_reason' => 'Reintento'])->assertNotFound();
        $this->assertSame(1, AuditLog::where('route_name', 'beneficiaries.destroy')->where('status_code', 200)->count());
    }

    public function test_group_deletion_preserves_every_identity_without_the_generic_payload_limit(): void
    {
        for ($i = 0; $i < 105; $i++) {
            $this->report->beneficiaries()->create(['full_name' => 'Persona '.$i, 'national_id' => (string) $i, 'age' => 20, 'sex' => 'Hombre']);
        }
        $this->delete(route('reports.destroy', $this->report), ['deletion_reason' => 'Listado de prueba'])->assertRedirect(route('reports.index'));
        $this->assertDatabaseMissing('reports', ['id' => $this->report->id]);
        $this->assertDatabaseCount('beneficiaries', 0);
        $logs = AuditLog::where('route_name', 'reports.destroy')->get();
        $this->assertCount(1, $logs);
        $snapshot = $logs->first()->request_payload['deletion'];
        $this->assertTrue($snapshot['report_deleted']);
        $this->assertCount(107, $snapshot['beneficiaries']);
        $this->assertSame('104', $snapshot['beneficiaries'][106]['national_id']);
        $this->assertNull($snapshot['beneficiaries'][1]['full_name']);
        $this->assertNull($snapshot['beneficiaries'][1]['national_id']);
    }

    public function test_deleting_the_last_person_also_audits_the_deleted_report(): void
    {
        $this->other->delete();
        $this->deleteJson(route('beneficiaries.destroy', $this->person), ['deletion_reason' => 'Prueba final'])
            ->assertOk()->assertJsonPath('report_deleted', true);
        $this->assertDatabaseMissing('reports', ['id' => $this->report->id]);
        $snapshot = AuditLog::where('route_name', 'beneficiaries.destroy')->sole()->request_payload['deletion'];
        $this->assertTrue($snapshot['report_deleted']);
        $this->assertSame($this->person->id, $snapshot['beneficiaries'][0]['id']);
    }

    public function test_audit_failure_rolls_back_both_deletion_paths(): void
    {
        AuditLog::creating(function (AuditLog $log): void {
            if (isset($log->request_payload['deletion'])) throw new \RuntimeException('Simulated audit failure');
        });
        try {
            foreach ([route('beneficiaries.destroy', $this->person), route('reports.destroy', $this->report)] as $url) {
                $this->deleteJson($url, ['deletion_reason' => 'Debe conservarse'])->assertStatus(500);
                $this->assertDatabaseHas('beneficiaries', ['id' => $this->person->id]);
                $this->assertDatabaseHas('beneficiaries', ['id' => $this->other->id]);
                $this->assertSame(2, $this->report->fresh()->total_beneficiaries);
            }
        } finally {
            AuditLog::flushEventListeners();
        }
        $this->assertSame(0, AuditLog::where('method', 'DELETE')->where('status_code', '<', 400)->count());
    }

    public function test_audit_details_are_admin_only_and_searchable_by_identity_or_reason(): void
    {
        $this->deleteJson(route('beneficiaries.destroy', $this->person), ['deletion_reason' => 'Carga duplicada'])->assertOk();
        $log = AuditLog::where('route_name', 'beneficiaries.destroy')->sole();
        foreach (['V00123456', 'PERSONA ELIMINADA', 'Carga duplicada'] as $search) {
            $this->getJson(route('audit-logs.data', ['method' => 'DELETE', 'search' => ['value' => $search]]))
                ->assertOk()->assertJsonPath('recordsFiltered', 1)->assertJsonPath('data.0.deletion_reason', 'Carga duplicada');
        }
        $reporter = User::factory()->create(['role' => 'reporter']);
        $this->actingAs($reporter)->getJson(route('audit-logs.show', $log))->assertForbidden()->assertDontSee('V00123456');
        $this->getJson(route('audit-logs.data'))->assertForbidden()->assertDontSee('PERSONA ELIMINADA');
        $this->deleteJson(route('reports.destroy', $this->report), ['deletion_reason' => 'Sin permiso'])->assertForbidden();
        $this->assertDatabaseHas('reports', ['id' => $this->report->id]);
    }

    public function test_closed_period_and_reviewed_beneficiaries_still_cannot_be_deleted(): void
    {
        ReportingPeriod::create(['period' => '2026-11', 'is_closed' => true]);
        foreach ([route('beneficiaries.destroy', $this->person), route('reports.destroy', $this->report)] as $url) {
            $this->deleteJson($url, ['deletion_reason' => 'Cierre'])->assertStatus(409);
        }
        ReportingPeriod::where('period', '2026-11')->update(['is_closed' => false]);
        $this->report->update(['status' => 'reviewed']);
        $this->deleteJson(route('beneficiaries.destroy', $this->person), ['deletion_reason' => 'Revisado'])->assertStatus(409);
        $this->assertDatabaseHas('beneficiaries', ['id' => $this->person->id]);
    }

    public function test_failed_deletion_cannot_forge_a_snapshot_in_the_audit_log(): void
    {
        $this->deleteJson(route('beneficiaries.destroy', $this->person), ['deletion' => ['reason' => 'Falso', 'beneficiaries' => [['national_id' => 'FAKE']]]])
            ->assertUnprocessable();
        $log = AuditLog::where('route_name', 'beneficiaries.destroy')->sole();
        $this->assertArrayNotHasKey('deletion', $log->request_payload);
    }

    public function test_names_with_accents_can_be_found_in_json_audit_snapshots(): void
    {
        $this->person->update(['full_name' => 'María Pérez']);
        $this->deleteJson(route('beneficiaries.destroy', $this->person), ['deletion_reason' => 'Corrección de prueba'])->assertOk();
        foreach (['MARÍA PÉREZ', 'Corrección de prueba'] as $search) {
            $this->getJson(route('audit-logs.data', ['method' => 'DELETE', 'search' => ['value' => $search]]))
                ->assertOk()->assertJsonPath('recordsFiltered', 1);
        }
    }

    public function test_a_registrar_with_permission_can_delete_only_their_allowed_beneficiaries(): void
    {
        $reporter = User::factory()->create(['role' => 'reporter']);
        $reporter->syncRoles([]);
        $reporter->givePermissionTo(['eliminar beneficiarios', 'ver detalle de registros']);
        $reporter->projects()->attach($this->report->proyecto_id);
        $reporter->assignedStates()->attach($this->report->state_id);
        $this->report->update(['user_id' => $reporter->id]);
        $this->actingAs($reporter)->deleteJson(route('beneficiaries.destroy', $this->person), ['deletion_reason' => 'Carga propia incorrecta'])
            ->assertOk()->assertJsonPath('report_deleted', false);
        $log = AuditLog::where('route_name', 'beneficiaries.destroy')->sole();
        $this->assertSame($reporter->id, $log->user_id);
        $this->getJson(route('audit-logs.show', $log))->assertForbidden();
        $this->deleteJson(route('beneficiaries.destroy', $this->other), ['deletion_reason' => 'Último'])->assertForbidden();
        $this->assertDatabaseHas('beneficiaries', ['id' => $this->other->id]);
    }

    public function test_report_forms_and_audit_detail_render_the_new_controls(): void
    {
        $this->get(route('reports.show', $this->report))->assertOk()
            ->assertSee('name="deletion_reason"', false)
            ->assertSee('js/report-deletion.js', false)
            ->assertSee('JSON.stringify({deletion_reason: reason})', false);
        $this->get(route('reports.create'))->assertOk()
            ->assertSee('js/report-deletion.js', false)
            ->assertSee('JSON.stringify({deletion_reason: reason})', false);
        $this->get(route('audit-logs.index'))->assertOk()
            ->assertSee('Motivo de eliminación')
            ->assertSee('Beneficiarios eliminados')
            ->assertSee('escapeHtml(person.national_id', false);
    }
}
