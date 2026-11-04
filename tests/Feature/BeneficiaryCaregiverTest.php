<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Beneficiary;
use App\Models\Indicador;
use App\Models\IndicadorProyecto;
use App\Models\Municipality;
use App\Models\Parish;
use App\Models\PlaceName;
use App\Models\Report;
use App\Models\Sector;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BeneficiaryCaregiverTest extends TestCase
{
    use RefreshDatabase;
    use \Tests\Concerns\CreatesReportProjectContext;

    private array $headers;

    protected function setUp(): void
    {
        parent::setUp();
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);
        $state = State::create(['code' => 'C', 'name' => 'Estado']);
        $municipality = Municipality::create(['state_id' => $state->id, 'code' => 'C1', 'name' => 'Municipio']);
        $parish = Parish::create(['municipality_id' => $municipality->id, 'code' => 'C11', 'name' => 'Parroquia']);
        $sector = Sector::create(['name' => 'Sector', 'slug' => 'caregiver']);
        PlaceName::create(['name' => 'Lugar de prueba']);
        $this->headers = $this->reportProjectContext($user, $state, $sector) + [
            'report_date' => today()->toDateString(), 'organization' => 'ASONACOP',
            'state_id' => $state->id, 'municipality_id' => $municipality->id, 'parish_id' => $parish->id,
            'installation_type' => 'Comunidad / Espacio Comunitario', 'place_name' => 'Lugar de prueba',
        ];
    }

    private function person(int $age = 17, bool $caregiver = true): array
    {
        return ['has_informed_consent' => true, 'full_name' => 'Persona de prueba', 'age' => $age, 'sex' => 'Mujer',
            'disability' => 'Ninguna', 'ethnicity' => 'Ninguna', 'pregnant_lactating' => 'Ninguna', 'is_recurrent' => false]
            + ($caregiver ? ['caregiver_national_id' => '00123456', 'caregiver_full_name' => 'Cuidadora Reservada', 'caregiver_relationship' => 'Madre'] : []);
    }

    public function test_under_18_accepts_missing_caregiver_fields_for_immediate_and_batch_registration(): void
    {
        foreach ([0, 5, 17] as $age) {
            $this->postJson(route('beneficiaries.store'), $this->headers + ['beneficiary' => $this->person($age, false)])
                ->assertCreated()->assertJsonPath('beneficiary.caregiver_national_id', null)
                ->assertJsonPath('beneficiary.caregiver_full_name', null)->assertJsonPath('beneficiary.caregiver_relationship', null);
            $this->postJson(route('reports.store'), $this->headers + ['beneficiaries' => [$this->person($age, false)]])
                ->assertSessionHasNoErrors()->assertRedirect();
        }
        $this->assertDatabaseCount('reports', 6);
        $this->assertDatabaseCount('beneficiaries', 6);
    }

    public function test_minor_caregiver_is_saved_loaded_for_edit_and_shown_without_affecting_counts_or_audit_privacy(): void
    {
        $response = $this->postJson(route('beneficiaries.store'), $this->headers + ['beneficiary' => $this->person()])
            ->assertCreated()->assertJsonPath('beneficiary.caregiver_national_id', '00123456')
            ->assertJsonPath('beneficiary.caregiver_full_name', 'CUIDADORA RESERVADA')->assertJsonPath('summary.total', 1);
        $person = Beneficiary::findOrFail($response->json('beneficiary.id'));
        $this->get(route('reports.edit', ['report' => $person->report_id, 'beneficiary' => $person->id]))->assertOk()
            ->assertViewHas('editingBeneficiary', fn ($item) => $item->caregiver_relationship === 'Madre');
        $this->get(route('reports.show', $person->report_id))->assertOk()
            ->assertSee('Datos de los cuidadores')->assertSee('CUIDADORA RESERVADA')->assertSee('00123456');
        $this->assertSame(1, Report::firstOrFail()->total_beneficiaries);
        $payload = json_encode(AuditLog::where('route_name', 'beneficiaries.store')->latest('id')->firstOrFail()->request_payload);
        $this->assertStringNotContainsString('Cuidadora Reservada', $payload);
        $this->assertStringNotContainsString('00123456', $payload);
        $this->assertStringContainsString('OCULTO', $payload);
    }

    public function test_adults_do_not_require_caregivers_and_stale_values_are_cleared_on_both_creation_paths(): void
    {
        foreach ([18, 75] as $age) {
            $this->postJson(route('beneficiaries.store'), $this->headers + ['beneficiary' => $this->person($age, false)])
                ->assertCreated()->assertJsonPath('beneficiary.caregiver_full_name', null);
        }
        $this->post(route('reports.store'), $this->headers + ['beneficiaries' => [$this->person(18)]])
            ->assertSessionHasNoErrors()->assertRedirect();
        foreach (Beneficiary::all() as $person) {
            $this->assertNull($person->caregiver_full_name);
            $this->assertNull($person->caregiver_national_id);
            $this->assertNull($person->caregiver_relationship);
        }
    }

    public function test_mixed_batches_validate_each_age_independently_without_partial_writes(): void
    {
        $this->postJson(route('reports.store'), $this->headers + ['beneficiaries' => [$this->person(30, false), $this->person(17, false)]])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseCount('reports', 1);
        $this->postJson(route('reports.store'), $this->headers + ['beneficiaries' => [$this->person(30, false),
            array_replace($this->person(17, false), ['caregiver_full_name' => ['invalid']])]])
            ->assertUnprocessable()->assertJsonValidationErrors('beneficiaries.1.caregiver_full_name');
        $this->assertDatabaseCount('reports', 1);
        $this->post(route('reports.store'), $this->headers + ['beneficiaries' => [$this->person(30), $this->person(0)]])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(4, Beneficiary::count());
        $this->assertNull(Beneficiary::orderBy('id')->first()->caregiver_national_id);
        $this->assertSame('00123456', Beneficiary::orderByDesc('id')->first()->caregiver_national_id);
    }

    public function test_nonstring_and_overlong_caregiver_fields_are_rejected_when_provided(): void
    {
        foreach (['caregiver_national_id' => 30, 'caregiver_full_name' => 150, 'caregiver_relationship' => 100] as $field => $max) {
            foreach ([['invalid'], str_repeat('A', $max + 1)] as $value) {
                $this->postJson(route('beneficiaries.store'), $this->headers + ['beneficiary' => array_replace($this->person(), [$field => $value])])
                    ->assertUnprocessable()->assertJsonValidationErrors('beneficiary.'.$field);
            }
        }
        $this->assertDatabaseCount('beneficiaries', 0);
    }

    public function test_both_edit_endpoints_allow_empty_caregiver_for_minors_and_clear_it_when_age_becomes_18(): void
    {
        $this->postJson(route('beneficiaries.store'), $this->headers + ['beneficiary' => $this->person()])->assertCreated();
        $person = Beneficiary::firstOrFail();
        $empty = array_replace($this->person(17, false), ['caregiver_national_id' => null,
            'caregiver_full_name' => null, 'caregiver_relationship' => null]);
        $this->putJson(route('beneficiaries.update', $person), $empty)
            ->assertOk()->assertJsonPath('beneficiary.caregiver_full_name', null);
        $this->putJson(route('beneficiaries.update-attention', $person), $this->headers + ['beneficiary' => $empty])
            ->assertOk()->assertJsonPath('beneficiary.caregiver_national_id', null);
        $this->putJson(route('beneficiaries.update', $person), $this->person(18))->assertOk();
        $this->assertNull($person->fresh()->caregiver_full_name);
        $this->putJson(route('beneficiaries.update-attention', $person), $this->headers + ['beneficiary' => $this->person(17)])
            ->assertOk()->assertJsonPath('beneficiary.caregiver_national_id', '00123456');
        $this->putJson(route('beneficiaries.update-attention', $person), $this->headers + ['beneficiary' => $this->person(18)])
            ->assertOk()->assertJsonPath('beneficiary.caregiver_full_name', null);
    }

    public function test_associated_indicators_copy_the_caregiver_without_creating_an_extra_caregiver_beneficiary(): void
    {
        $principal = IndicadorProyecto::findOrFail($this->headers['indicador_proyecto_id']);
        $indicator = Indicador::create(['codigo' => 'ASSOC-CARE', 'descripcion' => 'Asociado', 'unidad_conteo' => 'Personas',
            'espacio_coordinacion' => 'NNA', 'edad_desde' => 0, 'edad_hasta' => 120]);
        $assignment = IndicadorProyecto::create(['proyecto_id' => $principal->proyecto_id, 'sector_proyecto_id' => $principal->sector_proyecto_id,
            'indicador_id' => $indicator->id, 'estatus' => true]);
        $principal->indicadoresAsociados()->attach($assignment);
        $this->postJson(route('beneficiaries.store'), $this->headers + ['associated_indicator_ids' => [$assignment->id], 'beneficiary' => $this->person()])
            ->assertCreated()->assertJsonCount(1, 'associated_reports');
        $this->assertDatabaseCount('beneficiaries', 2);
        foreach (Beneficiary::all() as $person) $this->assertSame('00123456', $person->caregiver_national_id);
    }

    public function test_new_migration_does_not_infer_or_backfill_historical_caregivers(): void
    {
        $this->postJson(route('beneficiaries.store'), $this->headers + ['beneficiary' => $this->person(30, false)])->assertCreated();
        $person = Beneficiary::firstOrFail();
        // Simulate a pre-existing minor without inventing a caregiver.
        $person->update(['age' => 12]);
        $migration = require database_path('migrations/2026_11_02_120000_add_caregiver_fields_to_beneficiaries.php');
        $migration->down();
        $migration->up();
        $person->refresh();
        $this->assertSame(12, (int) $person->age);
        $this->assertNull($person->caregiver_full_name);
        $this->assertNull($person->caregiver_national_id);
        $this->assertNull($person->caregiver_relationship);
        $this->assertDatabaseCount('beneficiaries', 1);
    }

    public function test_relationship_selector_offers_common_options_and_server_accepts_only_available_choices(): void
    {
        $page = $this->get(route('reports.create'))->assertOk()
            ->assertSee('<select id="beneficiary_caregiver_relationship" disabled>', false)
            ->assertSee('Seleccione el parentesco')->assertDontSee('<input type="text" id="beneficiary_caregiver_relationship"', false);
        foreach (config('reports.beneficiary_options.caregiver_relationships') as $option) {
            $page->assertSee('value="'.$option.'" data-caregiver-relationship-legacy="0"', false);
        }
        foreach (['Padre', 'Abuela', 'Tutor/a legal', 'Otro'] as $option) {
            $this->postJson(route('beneficiaries.store'), $this->headers + ['beneficiary' => array_replace($this->person(), ['caregiver_relationship' => $option])])
                ->assertCreated()->assertJsonPath('beneficiary.caregiver_relationship', $option);
        }
        $person = array_replace($this->person(), ['caregiver_relationship' => 'Parentesco no disponible']);
        $this->postJson(route('beneficiaries.store'), $this->headers + ['beneficiary' => $person])
            ->assertUnprocessable()->assertJsonValidationErrors('beneficiary.caregiver_relationship');
        $this->postJson(route('reports.store'), $this->headers + ['beneficiaries' => [$person]])
            ->assertUnprocessable()->assertJsonValidationErrors('beneficiaries.0.caregiver_relationship');
    }

    public function test_blank_or_partial_caregiver_fields_are_optional_and_partial_data_is_visible_in_detail(): void
    {
        foreach (['caregiver_national_id', 'caregiver_full_name', 'caregiver_relationship'] as $field) {
            foreach ([null, '', '   '] as $empty) {
                $person = array_replace($this->person(), [$field => $empty]);
                $this->postJson(route('beneficiaries.store'), $this->headers + ['beneficiary' => $person])
                    ->assertCreated()->assertJsonPath('beneficiary.'.$field, null);
            }
        }
        $result = $this->postJson(route('beneficiaries.store'), $this->headers + ['beneficiary' =>
            $this->person(12, false) + ['caregiver_national_id' => '00123456']])->assertCreated();
        $this->get(route('reports.show', $result->json('report.id')))->assertOk()
            ->assertSee('Datos de los cuidadores')->assertSee('00123456');
        $this->get(route('reports.create'))->assertOk()->assertSee('Datos opcionales para beneficiarios menores de 18 años.')
            ->assertDontSee('Obligatorios para beneficiarios menores de 18 años.');
    }

    public function test_legacy_relationship_is_preserved_only_for_its_original_beneficiary_when_editing(): void
    {
        $this->postJson(route('beneficiaries.store'), $this->headers + ['beneficiary' => $this->person()])->assertCreated();
        $person = Beneficiary::firstOrFail();
        $person->update(['caregiver_relationship' => 'Vecina de confianza']);
        $this->get(route('reports.edit', ['report' => $person->report_id, 'beneficiary' => $person->id]))->assertOk()
            ->assertSee('value="Vecina de confianza" data-caregiver-relationship-legacy="1" disabled', false);
        $payload = array_replace($this->person(), ['caregiver_relationship' => 'Vecina de confianza']);
        $this->putJson(route('beneficiaries.update', $person), $payload)->assertOk()
            ->assertJsonPath('beneficiary.caregiver_relationship', 'Vecina de confianza');
        $this->putJson(route('beneficiaries.update-attention', $person), $this->headers + ['beneficiary' => $payload])
            ->assertOk()->assertJsonPath('beneficiary.caregiver_relationship', 'Vecina de confianza');
        $this->postJson(route('beneficiaries.store'), $this->headers + ['beneficiary' => $payload])
            ->assertUnprocessable()->assertJsonValidationErrors('beneficiary.caregiver_relationship');
        $this->putJson(route('beneficiaries.update', $person), array_replace($payload, ['caregiver_relationship' => 'Texto diferente']))
            ->assertUnprocessable()->assertJsonValidationErrors('caregiver_relationship');
        $this->assertSame('Vecina de confianza', $person->fresh()->caregiver_relationship);
    }
}
