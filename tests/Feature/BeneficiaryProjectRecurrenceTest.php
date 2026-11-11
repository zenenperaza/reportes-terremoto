<?php

namespace Tests\Feature;

use App\Models\{AuditLog, Beneficiary, IndicadorProyecto, Municipality, Parish, PlaceName, Report, Sector, State, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BeneficiaryProjectRecurrenceTest extends TestCase
{
    use RefreshDatabase;
    use \Tests\Concerns\CreatesReportProjectContext;

    private User $admin;
    private array $headers;
    private array $other;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($this->admin);
        $state = State::create(['code' => 'R', 'name' => 'Estado']);
        $municipality = Municipality::create(['state_id' => $state->id, 'code' => 'R1', 'name' => 'Municipio']);
        $parish = Parish::create(['municipality_id' => $municipality->id, 'code' => 'R11', 'name' => 'Parroquia']);
        $sector = Sector::create(['name' => 'Sector', 'slug' => 'recurrence']);
        PlaceName::create(['name' => 'Lugar']);
        $this->headers = $this->reportProjectContext($this->admin, $state, $sector) + [
            'report_date' => today()->toDateString(), 'organization' => 'ASONACOP', 'state_id' => $state->id,
            'municipality_id' => $municipality->id, 'parish_id' => $parish->id,
            'installation_type' => 'Comunidad / Espacio Comunitario', 'place_name' => 'Lugar',
        ];
        $this->other = array_replace($this->headers, $this->reportProjectContext($this->admin, $state, $sector));
    }

    private function person(array $values = []): array
    {
        return array_replace(['has_informed_consent' => true, 'full_name' => 'María Pérez', 'age' => 34,
            'sex' => 'Mujer', 'national_id' => 'V12345678', 'disability' => 'Ninguna', 'ethnicity' => 'Ninguna',
            'pregnant_lactating' => 'Ninguna', 'is_recurrent' => false], $values);
    }

    private function record(array $headers, array $person = [], ?User $owner = null): Beneficiary
    {
        $owner ??= $this->admin;
        $report = Report::create($headers + ['user_id' => $owner->id, 'reporter_first_name' => 'Prueba',
            'reporter_last_name' => '', 'reporter_email' => $owner->email, 'total_beneficiaries' => 1,
            'recurrence_status' => 'no_recurrente', 'beneficiary_breakdown' => []]);
        return $report->beneficiaries()->create($this->person($person));
    }

    private function check(array $person = [], ?int $project = null)
    {
        return $this->postJson(route('beneficiaries.project-recurrence'), ['proyecto_id' => $project ?? $this->headers['proyecto_id']] + $this->person($person));
    }

    public function test_same_project_matches_across_indicator_location_period_and_author_without_returning_profile(): void
    {
        $outsider = User::factory()->create(['role' => 'reporter']);
        $person = $this->record(array_replace($this->headers, ['indicador_proyecto_id' => null, 'place_name' => 'Otro lugar',
            'reporting_period' => '2025-01']), [], $outsider);
        $user = User::factory()->create(['role' => 'reporter']);
        $user->givePermissionTo('registrar actividad');
        $user->projects()->attach($this->headers['proyecto_id']);
        $this->actingAs($user);
        $this->check()->assertOk()->assertExactJson(['comparable' => true, 'is_recurrent' => true, 'matched_by' => 'cédula y proyecto'])
            ->assertHeader('Cache-Control', 'no-store, private')->assertDontSee($person->full_name);
        $this->assertNull(AuditLog::where('route_name', 'beneficiaries.project-recurrence')->firstOrFail()->request_payload);
    }

    public function test_another_project_or_missing_identity_never_matches(): void
    {
        $this->record($this->other);
        $this->check()->assertOk()->assertJsonPath('is_recurrent', false);
        $this->postJson(route('beneficiaries.project-recurrence'), ['proyecto_id' => $this->headers['proyecto_id']])
            ->assertOk()->assertJsonPath('is_recurrent', false)->assertJsonPath('comparable', false);
    }

    public function test_cedula_formats_and_prefixes_match_without_using_guardian_id_or_another_explicit_prefix(): void
    {
        $this->record($this->headers, ['national_id' => 'E-00.123.456', 'caregiver_national_id' => '99887766']);
        foreach (['E00123456', 'e-00.123.456', '00123456'] as $id) $this->check(['national_id' => $id])->assertJsonPath('is_recurrent', true);
        foreach (['V00123456', '123456', '99887766'] as $id) $this->check(['national_id' => $id])->assertJsonPath('is_recurrent', false);
    }

    public function test_without_cedula_exact_name_age_and_sex_match_independent_of_place(): void
    {
        $this->record($this->headers, ['national_id' => null, 'full_name' => 'Ana  Pérez', 'age' => 12]);
        $this->check(['national_id' => null, 'full_name' => 'ana pérez', 'age' => 12])->assertJsonPath('is_recurrent', true);
        foreach ([['age' => 13], ['sex' => 'Hombre'], ['full_name' => 'Ana Gómez'], ['national_id' => '11111111']] as $change) {
            $this->check(array_replace(['national_id' => null, 'full_name' => 'Ana Pérez', 'age' => 12], $change))->assertJsonPath('is_recurrent', false);
        }
    }

    public function test_project_assignment_and_permission_are_required(): void
    {
        $user = User::factory()->create(['role' => 'reporter']); $user->syncRoles([]);
        $this->actingAs($user); $this->check()->assertForbidden();
        $user->givePermissionTo('registrar actividad'); $this->actingAs($user->fresh());
        $this->check()->assertForbidden();
        $user->projects()->attach($this->headers['proyecto_id']);
        $this->check()->assertOk();
        $this->check([], $this->other['proyecto_id'])->assertForbidden();
    }

    public function test_immediate_save_preserves_explicit_no_even_with_prior_attendance_in_same_project(): void
    {
        $first = $this->postJson(route('beneficiaries.store'), $this->headers + ['beneficiary' => $this->person(['is_recurrent' => false])])
            ->assertCreated()->assertJsonPath('beneficiary.is_recurrent', false)->assertJsonPath('summary.recurrence_status', 'no_recurrente');
        $this->check()->assertOk()->assertJsonPath('is_recurrent', true);
        $this->postJson(route('beneficiaries.store'), $this->headers + ['beneficiary' => $this->person(['is_recurrent' => false, 'recurrence_manual' => true])])
            ->assertCreated()->assertJsonPath('beneficiary.is_recurrent', false)->assertJsonPath('summary.recurrence_status', 'no_recurrente');
        $this->postJson(route('beneficiaries.store'), $this->headers + ['beneficiary' => $this->person(['is_recurrent' => '1', 'recurrence_manual' => true])])
            ->assertCreated()->assertJsonPath('beneficiary.is_recurrent', true)->assertJsonPath('summary.recurrence_status', 'recurrente');
        $this->postJson(route('beneficiaries.store'), $this->other + ['beneficiary' => $this->person(['is_recurrent' => true, 'recurrence_manual' => true])])
            ->assertCreated()->assertJsonPath('beneficiary.is_recurrent', true);
        $this->assertFalse(Beneficiary::findOrFail($first->json('beneficiary.id'))->is_recurrent);
    }

    public function test_save_without_recurrence_field_still_computes_it_and_repeated_rows_under_same_report_are_detected(): void
    {
        $person = $this->person(); unset($person['is_recurrent']);
        $first = $this->postJson(route('beneficiaries.store'), $this->headers + ['beneficiary' => $person])->assertCreated();
        $this->postJson(route('beneficiaries.store'), $this->headers + ['report_id' => $first->json('report.id'), 'beneficiary' => $person])
            ->assertOk()->assertJsonPath('beneficiary.is_recurrent', true)->assertJsonPath('summary.recurrence_status', 'mixto');
    }

    public function test_batch_computes_recurrence_before_associated_copies_and_preserves_first_attendance(): void
    {
        $person = $this->person(); unset($person['is_recurrent']);
        $this->post(route('reports.store'), $this->headers + ['beneficiaries' => [$person, $person]])->assertSessionHasNoErrors()->assertRedirect();
        $people = Beneficiary::orderBy('id')->get();
        $this->assertFalse($people[0]->is_recurrent); $this->assertTrue($people[1]->is_recurrent);
        $this->assertSame('mixto', Report::firstOrFail()->recurrence_status);
    }

    public function test_form_loads_project_recurrence_and_existing_edits_do_not_reclassify_historical_rows(): void
    {
        $this->get(route('reports.create'))->assertOk()->assertSee('beneficiary-recurrence.js')->assertSee('Se sugiere automáticamente')
            ->assertSee('Puede cambiar la selección antes de guardar.');
        $first = $this->record($this->headers);
        $this->record($this->headers);
        $this->putJson(route('beneficiaries.update', $first), $this->person())->assertOk()->assertJsonPath('beneficiary.is_recurrent', false);
    }

    public function test_associated_indicator_copies_share_the_original_recurrence_without_matching_their_own_new_copies(): void
    {
        $indicator = \App\Models\Indicador::create(['codigo' => 'ASSOCIATED', 'descripcion' => 'Asociado',
            'unidad_conteo' => 'Personas', 'espacio_coordinacion' => 'NNA', 'edad_desde' => 0, 'edad_hasta' => 120]);
        $associated = IndicadorProyecto::create(['proyecto_id' => $this->headers['proyecto_id'],
            'sector_proyecto_id' => $this->headers['sector_proyecto_id'], 'indicador_id' => $indicator->id, 'estatus' => true]);
        IndicadorProyecto::findOrFail($this->headers['indicador_proyecto_id'])->indicadoresAsociados()->attach($associated);
        $person = $this->person(); unset($person['is_recurrent']);
        $payload = $this->headers + ['beneficiary' => $person, 'associated_indicator_ids' => [$associated->id]];
        $this->postJson(route('beneficiaries.store'), $payload)->assertCreated()->assertJsonCount(1, 'associated_reports');
        $this->assertSame([false, false], Beneficiary::orderBy('id')->pluck('is_recurrent')->all());
        $this->postJson(route('beneficiaries.store'), $payload)->assertCreated();
        $this->assertSame([false, false, true, true], Beneficiary::orderBy('id')->pluck('is_recurrent')->all());
        $payload['beneficiary']['is_recurrent'] = '0';
        $payload['beneficiary']['recurrence_manual'] = true;
        $this->postJson(route('beneficiaries.store'), $payload)->assertCreated();
        $this->assertSame([false, false, true, true, false, false], Beneficiary::orderBy('id')->pluck('is_recurrent')->all());
    }

    public function test_batch_preserves_explicit_choices_and_computes_only_omitted_values(): void
    {
        $this->record($this->headers);
        $automatic = $this->person(); unset($automatic['is_recurrent']);
        $this->post(route('reports.store'), $this->headers + ['beneficiaries' => [
            $this->person(['is_recurrent' => '0', 'recurrence_manual' => true]), $automatic,
            $this->person(['is_recurrent' => '1', 'recurrence_manual' => true]),
        ]])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame([false, false, true, true], Beneficiary::orderBy('id')->pluck('is_recurrent')->all());
        $this->assertSame('mixto', Report::orderByDesc('id')->firstOrFail()->recurrence_status);
    }

    public function test_manual_no_is_preserved_without_cedula_and_when_appending_to_existing_list(): void
    {
        $first = $this->record($this->headers, ['national_id' => null]);
        $this->check(['national_id' => null])->assertOk()->assertJsonPath('is_recurrent', true);
        $this->postJson(route('beneficiaries.store'), $this->headers + ['report_id' => $first->report_id,
            'beneficiary' => $this->person(['national_id' => null, 'is_recurrent' => '0', 'recurrence_manual' => true])])
            ->assertOk()->assertJsonPath('beneficiary.is_recurrent', false)->assertJsonPath('summary.recurrence_status', 'no_recurrente');
    }

    public function test_manual_recurrence_still_rejects_non_boolean_values(): void
    {
        foreach (['No', 'Sí', 2, null, ['0']] as $invalid) {
            $this->postJson(route('beneficiaries.store'), $this->headers + ['beneficiary' => $this->person(['is_recurrent' => $invalid])])
                ->assertUnprocessable()->assertJsonValidationErrors('beneficiary.is_recurrent');
        }
        $this->assertSame(0, Beneficiary::count());
    }

    public function test_without_manual_correction_server_rechecks_automatic_values_when_saving(): void
    {
        $first = $this->postJson(route('beneficiaries.store'), $this->headers + ['beneficiary' => $this->person(['is_recurrent' => true])])
            ->assertCreated()->assertJsonPath('beneficiary.is_recurrent', false);
        $this->postJson(route('beneficiaries.store'), $this->headers + ['beneficiary' => $this->person(['is_recurrent' => false, 'recurrence_manual' => false])])
            ->assertCreated()->assertJsonPath('beneficiary.is_recurrent', true);
        $this->assertFalse(Beneficiary::findOrFail($first->json('beneficiary.id'))->is_recurrent);
    }

    public function test_manual_flag_is_validated_and_requires_an_explicit_recurrence_value(): void
    {
        foreach (['yes', 2, null, ['1']] as $invalid) {
            $this->postJson(route('beneficiaries.store'), $this->headers + ['beneficiary' => $this->person(['recurrence_manual' => $invalid])])
                ->assertUnprocessable()->assertJsonValidationErrors('beneficiary.recurrence_manual');
        }
        $person = $this->person(['recurrence_manual' => true]); unset($person['is_recurrent']);
        $this->postJson(route('beneficiaries.store'), $this->headers + ['beneficiary' => $person])
            ->assertUnprocessable()->assertJsonValidationErrors('beneficiary.is_recurrent');
        $this->post(route('reports.store'), $this->headers + ['beneficiaries' => [$this->person(), $person]])
            ->assertSessionHasErrors('beneficiaries.1.is_recurrent');
        $this->assertSame(0, Beneficiary::count());
    }

    public function test_browser_form_string_flags_preserve_manual_no_and_leave_automatic_rechecks_enabled(): void
    {
        $this->record($this->headers);
        $this->post(route('beneficiaries.store'), $this->headers + [
            'beneficiary' => $this->person(['is_recurrent' => '0', 'recurrence_manual' => '1']),
        ], ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('beneficiary.is_recurrent', false);
        $this->post(route('beneficiaries.store'), $this->headers + [
            'beneficiary' => $this->person(['is_recurrent' => '0', 'recurrence_manual' => '0']),
        ], ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('beneficiary.is_recurrent', true);
    }
}
