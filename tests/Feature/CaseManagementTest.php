<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CaseEvent;
use App\Models\CaseRecord;
use App\Models\Municipality;
use App\Models\State;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CaseManagementTest extends TestCase
{
    use RefreshDatabase;

    private function worker(array $extra = []): User
    {
        $user = User::factory()->create(['role' => 'reporter', 'is_active' => true]);
        $user->givePermissionTo(array_merge(['ver casos', 'crear casos', 'editar casos', 'ver historial de casos'], $extra));

        return $user;
    }

    private function payload(array $extra = []): array
    {
        return array_replace([
            'full_name' => 'Persona de prueba', 'registered_on' => today()->toDateString(), 'case_type' => 'general',
            'sex' => 'not_specified', 'risk_level' => 'pending', 'consent_status' => 'pending',
            'share_services' => '0', 'share_reports' => '0',
        ], $extra);
    }

    private function record(User $user, array $extra = []): CaseRecord
    {
        return CaseRecord::create($this->payload($extra) + ['reference' => 'CS-'.Str::ulid(), 'assigned_to' => $user->id, 'created_by' => $user->id]);
    }

    public function test_case_creation_supports_children_adults_and_vbg_with_private_history(): void
    {
        $worker = $this->worker(['gestionar casos vbg']);
        foreach ([[8, 'child_protection'], [35, 'general'], [72, 'vbg']] as [$age, $type]) {
            $response = $this->actingAs($worker)->post(route('cases.store'), $this->payload([
                'age_at_registration' => $age, 'case_type' => $type, 'presenting_needs' => 'Información confidencial de prueba',
            ]));
            $response->assertSessionHasNoErrors()->assertRedirect();
        }
        $this->assertSame(3, CaseRecord::count());
        $case = CaseRecord::latest('id')->firstOrFail();
        $this->assertSame($worker->id, $case->assigned_to);
        $this->assertSame('72 años al registrar (estimada)', $case->age_label);
        $this->assertSame(3, CaseEvent::where('action', 'created')->count());
        $this->assertNull(AuditLog::where('route_name', 'cases.store')->latest('id')->firstOrFail()->request_payload);
        $this->get(route('cases.show', $case))->assertOk()->assertSee('Consentimiento y confidencialidad')->assertHeader('Cache-Control', 'no-store, private');
        $this->get(route('cases.edit', $case))->assertOk()->assertSee('Guardar expediente');
        $this->get(route('cases.history', $case))->assertOk()->assertSee('Consultó el expediente');
        $this->assertDatabaseHas('case_events', ['case_record_id' => $case->id, 'action' => 'viewed']);
    }

    public function test_group_membership_does_not_grant_case_access_or_duplicate_search_visibility(): void
    {
        $owner = $this->worker();
        $peer = $this->worker();
        $group = UserGroup::create(['name' => 'Grupo de prueba', 'is_active' => true]);
        $owner->userGroups()->attach($group);
        $peer->userGroups()->attach($group);
        $case = $this->record($owner, ['full_name' => 'Persona reservada', 'document_number' => 'ID-789']);
        $this->actingAs($peer)->get(route('cases.index'))->assertOk()->assertDontSee('Persona reservada');
        $this->get(route('cases.start', ['q' => 'ID-789', 'search_by' => 'id']))->assertOk()->assertDontSee('Persona reservada')->assertSee('0 posibles coincidencias');
        $this->get(route('cases.show', $case))->assertForbidden();
        $this->get(route('cases.edit', $case))->assertForbidden();
        $this->put(route('cases.update', $case), $this->payload(['version' => 1]))->assertForbidden();
        $this->get(route('cases.history', $case))->assertForbidden();
        $this->assertSame(0, CaseEvent::count());
    }

    public function test_vbg_requires_specific_permission_even_for_a_supervisor(): void
    {
        $owner = $this->worker(['gestionar casos vbg']);
        $supervisor = $this->worker(['supervisar casos']);
        $case = $this->record($owner, ['case_type' => 'vbg', 'full_name' => 'Caso VBG reservado']);
        $this->actingAs($supervisor)->get(route('cases.index'))->assertOk()->assertDontSee('Caso VBG reservado');
        $this->get(route('cases.show', $case))->assertForbidden();
        $this->post(route('cases.store'), $this->payload(['case_type' => 'vbg']))->assertSessionHasErrors('case_type');
        $supervisor->givePermissionTo('gestionar casos vbg');
        $this->get(route('cases.show', $case))->assertOk();
    }

    public function test_reassignment_requires_permission_and_revokes_previous_owner_access(): void
    {
        $owner = $this->worker();
        $next = $this->worker();
        $case = $this->record($owner);
        $payload = $this->payload(['assigned_to' => $next->id, 'version' => 1]);
        $this->actingAs($owner)->put(route('cases.update', $case), $payload)->assertSessionHasErrors('assigned_to');
        $this->assertSame($owner->id, $case->fresh()->assigned_to);
        $owner->givePermissionTo('asignar casos');
        $this->put(route('cases.update', $case), $payload)->assertSessionHasNoErrors()->assertRedirect(route('cases.index'));
        $this->assertSame($next->id, $case->fresh()->assigned_to);
        $this->get(route('cases.show', $case))->assertForbidden();
        $this->actingAs($next)->get(route('cases.show', $case))->assertOk();
        $this->assertDatabaseHas('case_events', ['case_record_id' => $case->id, 'action' => 'assigned']);
    }

    public function test_validation_and_concurrent_edits_protect_existing_case_data(): void
    {
        $owner = $this->worker();
        $case = $this->record($owner);
        $this->actingAs($owner)->post(route('cases.store'), $this->payload(['share_services' => '1']))->assertSessionHasErrors('consent_status');
        $this->post(route('cases.store'), $this->payload(['consent_status' => 'granted']))->assertSessionHasErrors(['consent_source', 'consent_date']);
        $this->post(route('cases.store'), $this->payload(['birth_date' => today()->addDay()->toDateString()]))->assertSessionHasErrors('birth_date');
        $first = State::create(['code' => 'A', 'name' => 'Estado A']);
        $second = State::create(['code' => 'B', 'name' => 'Estado B']);
        $municipality = Municipality::create(['state_id' => $first->id, 'code' => 'A1', 'name' => 'Municipio A']);
        $this->post(route('cases.store'), $this->payload(['state_id' => $second->id, 'municipality_id' => $municipality->id]))->assertSessionHasErrors('municipality_id');
        $this->put(route('cases.update', $case), $this->payload(['version' => 1, 'full_name' => 'Nombre actualizado']))->assertSessionHasNoErrors();
        $this->put(route('cases.update', $case), $this->payload(['version' => 1, 'full_name' => 'Versión obsoleta']))->assertSessionHasErrors('version');
        $this->assertSame('Nombre actualizado', $case->fresh()->full_name);
        $this->assertSame(2, $case->fresh()->version);
        $this->assertSame(['full_name'], CaseEvent::where('action', 'updated')->firstOrFail()->changed_fields);
    }

    public function test_list_pagination_search_and_history_permissions_are_enforced(): void
    {
        $worker = $this->worker();
        for ($i = 1; $i <= 21; $i++) {
            $this->record($worker, ['full_name' => 'Persona '.$i, 'document_number' => 'DOC-'.$i]);
        }
        $this->actingAs($worker)->get(route('cases.index'))->assertOk()->assertViewHas('cases', fn ($cases) => $cases->total() === 21 && $cases->count() === 20);
        $this->get(route('cases.index', ['q' => 'DOC-1', 'search_by' => 'id']))->assertOk()->assertViewHas('cases', fn ($cases) => $cases->total() === 1);
        $worker->revokePermissionTo('ver historial de casos');
        $this->get(route('cases.history', CaseRecord::first()))->assertForbidden();
        $worker->revokePermissionTo('crear casos');
        $this->get(route('cases.create'))->assertForbidden();
        $this->get(route('cases.start'))->assertForbidden();
        $this->post(route('cases.store'), $this->payload())->assertForbidden();
    }

    public function test_changing_state_clears_previous_dependent_locations_and_birth_date_takes_priority(): void
    {
        $worker = $this->worker();
        $first = State::create(['code' => 'A', 'name' => 'Estado A']);
        $second = State::create(['code' => 'B', 'name' => 'Estado B']);
        $municipality = Municipality::create(['state_id' => $first->id, 'code' => 'A1', 'name' => 'Municipio A']);
        $case = $this->record($worker, ['state_id' => $first->id, 'municipality_id' => $municipality->id]);
        $this->actingAs($worker)->put(route('cases.update', $case), $this->payload([
            'state_id' => $second->id, 'version' => 1, 'birth_date' => today()->subYears(24)->toDateString(), 'age_at_registration' => 8,
        ]))->assertSessionHasNoErrors();
        $case->refresh();
        $this->assertSame($second->id, $case->state_id);
        $this->assertNull($case->municipality_id);
        $this->assertNull($case->age_at_registration);
        $this->assertSame('24 años', $case->age_label);
    }
}
