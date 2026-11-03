<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Beneficiary;
use App\Models\Municipality;
use App\Models\Parish;
use App\Models\Report;
use App\Models\State;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BeneficiaryIdAutofillTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($this->admin);
    }

    private function record(User $owner, array $data = []): Beneficiary
    {
        $state = State::firstOrCreate(['code' => 'L'], ['name' => 'Estado']);
        $municipality = Municipality::firstOrCreate(['code' => 'L1'], ['state_id' => $state->id, 'name' => 'Municipio']);
        $parish = Parish::firstOrCreate(['code' => 'L11'], ['municipality_id' => $municipality->id, 'name' => 'Parroquia']);
        $report = Report::create(['user_id' => $owner->id, 'report_date' => today()->toDateString(),
            'state_id' => $state->id, 'municipality_id' => $municipality->id, 'parish_id' => $parish->id,
            'reporter_first_name' => 'Prueba', 'reporter_last_name' => 'Consulta', 'reporter_email' => $owner->email,
            'organization' => 'ASONACOP', 'installation_type' => 'Comunidad / Espacio Comunitario',
            'place_name' => 'Lugar', 'recurrence_status' => 'nuevo', 'total_beneficiaries' => 1, 'beneficiary_breakdown' => []]);

        return $report->beneficiaries()->create(array_replace(['has_informed_consent' => true, 'national_id' => 'V-12.345.678',
            'full_name' => 'María Pérez', 'age' => 34, 'sex' => 'Mujer', 'nationality' => 'Venezolana', 'phone' => '04140000000',
            'disability' => 'Ninguna', 'ethnicity' => 'Ninguna', 'pregnant_lactating' => 'Ninguna', 'is_recurrent' => true], $data));
    }

    public function test_prefills_latest_visible_profile_without_record_metadata_consent_or_recurrence_and_without_writes(): void
    {
        $this->record($this->admin, ['phone' => '04140000001']);
        $this->record($this->admin, ['phone' => '04140000002', 'age' => 35]);
        $response = $this->postJson(route('beneficiaries.lookup'), ['national_id' => '12.345.678'])->assertOk()
            ->assertJsonPath('matched', true)->assertJsonPath('ambiguous', false)
            ->assertJsonPath('beneficiary.full_name', 'MARÍA PÉREZ')->assertJsonPath('beneficiary.phone', '04140000002')
            ->assertJsonPath('beneficiary.age', 35)->assertHeader('Cache-Control', 'no-store, private');
        foreach (['id', 'report_id', 'national_id', 'has_informed_consent', 'is_recurrent', 'reported_at', 'created_at'] as $field) {
            $response->assertJsonMissingPath('beneficiary.'.$field);
        }
        $this->assertDatabaseCount('beneficiaries', 2);
        $this->assertDatabaseCount('reports', 2);
        $this->assertDatabaseCount('system_notifications', 0);
        $this->assertNull(AuditLog::where('route_name', 'beneficiaries.lookup')->firstOrFail()->request_payload);
    }

    public function test_form_places_optional_national_id_first_and_keeps_manual_fields_editable(): void
    {
        $response = $this->get(route('reports.create'))->assertOk()->assertSee('beneficiary-id-autofill.js');
        $response->assertSeeInOrder(['id="beneficiary_national_id"', 'id="beneficiary_full_name"', 'id="beneficiary_age"', 'id="beneficiary_sex"'], false);
        $this->assertSame(1, substr_count($response->getContent(), 'id="beneficiary_national_id"'));
        $response->assertSee('data-lookup-enabled="1"', false);
    }

    public function test_normalizes_spaces_dots_and_hyphens_but_never_uses_partial_ids_or_drops_leading_zeros(): void
    {
        $this->record($this->admin, ['national_id' => 'E-00.123.456']);
        foreach (['E00123456', 'e-00.123.456', '00123456'] as $id) {
            $this->postJson(route('beneficiaries.lookup'), ['national_id' => $id])->assertOk()->assertJsonPath('matched', true);
        }
        foreach (['123456', '0012345', 'V00123456'] as $id) {
            $this->postJson(route('beneficiaries.lookup'), ['national_id' => $id])->assertOk()->assertJsonPath('matched', false);
        }
    }

    public function test_conflicting_names_sexes_or_identity_prefixes_require_manual_review_without_returning_profiles(): void
    {
        $first = $this->record($this->admin);
        $other = $this->record($this->admin, ['full_name' => 'Identidad distinta']);
        $this->postJson(route('beneficiaries.lookup'), ['national_id' => '12345678'])->assertOk()
            ->assertExactJson(['matched' => false, 'ambiguous' => true]);
        $other->update(['full_name' => $first->full_name, 'sex' => 'Hombre']);
        $this->postJson(route('beneficiaries.lookup'), ['national_id' => '12345678'])->assertOk()
            ->assertExactJson(['matched' => false, 'ambiguous' => true]);
        $other->update(['sex' => $first->sex, 'national_id' => 'E12345678']);
        $this->postJson(route('beneficiaries.lookup'), ['national_id' => '12345678'])->assertOk()
            ->assertExactJson(['matched' => false, 'ambiguous' => true]);
        $this->postJson(route('beneficiaries.lookup'), ['national_id' => 'V12345678'])->assertOk()->assertJsonPath('matched', true);
    }

    public function test_group_scope_is_respected_and_hidden_matches_do_not_leak_or_create_ambiguity(): void
    {
        $reader = User::factory()->create(['role' => 'reporter']);
        $reader->givePermissionTo(['registrar actividad', 'ver detalle de registros']);
        $peer = User::factory()->create(['role' => 'reporter']);
        $outsider = User::factory()->create(['role' => 'reporter']);
        $group = UserGroup::create(['name' => 'Equipo', 'is_active' => true]);
        $group->users()->attach([$reader->id, $peer->id]);
        $this->record($peer, ['full_name' => 'Persona de su equipo']);
        $this->record($outsider, ['full_name' => 'Persona oculta']);
        $this->record($outsider, ['national_id' => 'V87654321', 'full_name' => 'Otra persona oculta']);
        $this->actingAs($reader)->postJson(route('beneficiaries.lookup'), ['national_id' => '12345678'])->assertOk()
            ->assertJsonPath('beneficiary.full_name', 'PERSONA DE SU EQUIPO')->assertDontSee('OCULTA');
        $this->postJson(route('beneficiaries.lookup'), ['national_id' => '87654321'])->assertOk()
            ->assertExactJson(['matched' => false, 'ambiguous' => false, 'beneficiary' => null]);
        $group->users()->detach($reader);
        $this->actingAs($reader->fresh())->postJson(route('beneficiaries.lookup'), ['national_id' => '12345678'])
            ->assertOk()->assertJsonPath('matched', false);
    }

    public function test_both_existing_permissions_are_required_and_invalid_inputs_do_not_return_data(): void
    {
        foreach (['registrar actividad', 'ver detalle de registros'] as $permission) {
            $reader = User::factory()->create(['role' => 'reporter']);
            $reader->syncRoles([]);
            $reader->givePermissionTo($permission);
            $this->actingAs($reader)->postJson(route('beneficiaries.lookup'), ['national_id' => '12345678'])->assertForbidden();
        }
        $this->actingAs($this->admin);
        foreach (['', '1234', 'VABCDEF', ['invalid'], str_repeat('1', 31)] as $id) {
            $this->postJson(route('beneficiaries.lookup'), ['national_id' => $id])->assertUnprocessable()->assertJsonValidationErrors('national_id');
        }
    }

    public function test_minor_profile_returns_saved_caregiver_fields_only_not_caregiver_id_matches(): void
    {
        $this->record($this->admin, ['age' => 12, 'caregiver_national_id' => '99887766',
            'caregiver_full_name' => 'Cuidadora', 'caregiver_relationship' => 'Madre']);
        $this->postJson(route('beneficiaries.lookup'), ['national_id' => '12345678'])->assertOk()
            ->assertJsonPath('beneficiary.caregiver_national_id', '99887766')->assertJsonPath('beneficiary.caregiver_full_name', 'CUIDADORA');
        $this->postJson(route('beneficiaries.lookup'), ['national_id' => '99887766'])->assertOk()->assertJsonPath('matched', false);
    }

    public function test_rate_limit_applies_to_repeated_identity_searches(): void
    {
        for ($index = 0; $index < 60; $index++) {
            $this->postJson(route('beneficiaries.lookup'), ['national_id' => '12345678'])->assertOk();
        }
        $this->postJson(route('beneficiaries.lookup'), ['national_id' => '12345678'])->assertStatus(429);
    }
}
