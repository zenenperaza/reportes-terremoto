<?php

namespace Tests\Feature;

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
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BeneficiaryNationalityTest extends TestCase
{
    use RefreshDatabase;
    use \Tests\Concerns\CreatesReportProjectContext;

    private array $headers;

    protected function setUp(): void
    {
        parent::setUp();
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);
        $state = State::create(['code' => 'N', 'name' => 'Estado']);
        $municipality = Municipality::create(['state_id' => $state->id, 'code' => 'N1', 'name' => 'Municipio']);
        $parish = Parish::create(['municipality_id' => $municipality->id, 'code' => 'N11', 'name' => 'Parroquia']);
        $sector = Sector::create(['name' => 'Sector', 'slug' => 'nacionalidad']);
        PlaceName::create(['name' => 'Lugar de prueba']);
        $this->headers = $this->reportProjectContext($user, $state, $sector) + [
            'report_date' => today()->toDateString(), 'organization' => 'ASONACOP',
            'state_id' => $state->id, 'municipality_id' => $municipality->id, 'parish_id' => $parish->id,
            'installation_type' => 'Comunidad / Espacio Comunitario', 'place_name' => 'Lugar de prueba',
        ];
    }

    private function person(array $overrides = []): array
    {
        return $overrides + ['has_informed_consent' => true, 'full_name' => 'Persona de prueba',
            'age' => 25, 'sex' => 'Mujer', 'disability' => 'Ninguna', 'ethnicity' => 'Ninguna',
            'pregnant_lactating' => 'Ninguna', 'is_recurrent' => false];
    }

    private function savePerson(array $overrides = []): Beneficiary
    {
        $response = $this->postJson(route('beneficiaries.store'), $this->headers + ['beneficiary' => $this->person($overrides)])
            ->assertCreated();
        return Beneficiary::findOrFail($response->json('beneficiary.id'));
    }

    private function nationalitySelect(string $html): \DOMXPath
    {
        $previous = libxml_use_internal_errors(true);
        $document = new \DOMDocument;
        $document->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        return new \DOMXPath($document);
    }

    public function test_new_form_select_defaults_to_venezuelan_and_listed_nationality_is_saved_and_editable(): void
    {
        $page = $this->get(route('reports.create'))->assertOk()->assertSee('id="beneficiary_nationality"', false)
            ->assertSee('Venezolana')->assertSee('<th>Nacionalidad</th>', false);
        $xpath = $this->nationalitySelect($page->getContent());
        $this->assertCount(1, $xpath->query('//select[@id="beneficiary_nationality"]'));
        $selected = $xpath->query('//select[@id="beneficiary_nationality"]/option[@selected]');
        $this->assertCount(1, $selected);
        $this->assertSame('Venezolana', $selected->item(0)->getAttribute('value'));
        foreach (config('reports.beneficiary_options.nationalities') as $nationality) {
            $this->assertCount(1, $xpath->query('//select[@id="beneficiary_nationality"]/option[@value="'.$nationality.'"]'));
        }
        $person = $this->savePerson(['nationality' => 'Colombiana']);
        $this->assertSame('Colombiana', $person->nationality);
        $this->get(route('reports.show', $person->report_id))->assertOk()
            ->assertSee('data-label="Nacionalidad">Colombiana', false);
        $this->get(route('reports.edit', ['report' => $person->report_id, 'beneficiary' => $person->id]))
            ->assertOk()->assertViewHas('editingBeneficiary', fn ($item) => $item->nationality === 'Colombiana');
        $this->putJson(route('beneficiaries.update', $person), $this->person(['nationality' => 'Peruana']))
            ->assertOk()->assertJsonPath('beneficiary.nationality', 'Peruana');
    }

    public function test_new_records_default_when_omitted_and_explicit_blank_stays_unspecified(): void
    {
        $this->assertSame('Venezolana', $this->savePerson()->nationality);
        $this->assertNull($this->savePerson(['nationality' => ''])->nationality);
        $this->assertSame(2, Beneficiary::count());
    }

    public function test_batch_save_preserves_each_nationality_and_only_defaults_missing_values(): void
    {
        $this->post(route('reports.store'), $this->headers + ['beneficiaries' => [
            $this->person(['nationality' => 'Colombiana']), $this->person(), $this->person(['nationality' => null]),
        ]])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(['Colombiana', 'Venezolana', null], Beneficiary::orderBy('id')->pluck('nationality')->all());
        $this->assertSame(3, Report::firstOrFail()->total_beneficiaries);
    }

    public function test_omitting_nationality_on_either_edit_endpoint_never_overwrites_existing_or_unknown_data(): void
    {
        $person = $this->savePerson(['nationality' => 'Colombiana']);
        $this->putJson(route('beneficiaries.update', $person), $this->person())->assertOk();
        $this->assertSame('Colombiana', $person->fresh()->nationality);
        $this->putJson(route('beneficiaries.update-attention', $person), $this->headers + ['beneficiary' => $this->person()])->assertOk();
        $this->assertSame('Colombiana', $person->fresh()->nationality);
        $person->update(['nationality' => null]);
        $this->putJson(route('beneficiaries.update-attention', $person), $this->headers + ['beneficiary' => $this->person()])->assertOk();
        $this->assertNull($person->fresh()->nationality);
        $this->putJson(route('beneficiaries.update-attention', $person), $this->headers + ['beneficiary' => $this->person(['nationality' => 'Ecuatoriana'])])
            ->assertOk()->assertJsonPath('beneficiary.nationality', 'Ecuatoriana');
    }

    public function test_validation_rejects_oversized_and_non_string_values_on_every_save_path(): void
    {
        $person = $this->savePerson(['nationality' => 'Colombiana']);
        foreach ([str_repeat('N', 81), ['not-a-string'], 'Inventada'] as $invalid) {
            $payload = $this->person(['nationality' => $invalid]);
            $this->postJson(route('beneficiaries.store'), $this->headers + ['beneficiary' => $payload])
                ->assertUnprocessable()->assertJsonValidationErrors('beneficiary.nationality');
            $this->postJson(route('reports.store'), $this->headers + ['beneficiaries' => [$payload]])
                ->assertUnprocessable()->assertJsonValidationErrors('beneficiaries.0.nationality');
            $this->putJson(route('beneficiaries.update', $person), $payload)
                ->assertUnprocessable()->assertJsonValidationErrors('nationality');
            $this->putJson(route('beneficiaries.update-attention', $person), $this->headers + ['beneficiary' => $payload])
                ->assertUnprocessable()->assertJsonValidationErrors('beneficiary.nationality');
        }
        $this->assertSame('Colombiana', $person->fresh()->nationality);
        $this->assertDatabaseCount('beneficiaries', 1);
    }

    public function test_legacy_nationality_outside_the_list_remains_available_only_for_its_existing_beneficiary(): void
    {
        $person = $this->savePerson();
        $person->update(['nationality' => 'Mongola']);
        $page = $this->get(route('reports.edit', ['report' => $person->report_id, 'beneficiary' => $person->id]))->assertOk();
        $xpath = $this->nationalitySelect($page->getContent());
        $this->assertCount(1, $xpath->query('//select[@id="beneficiary_nationality"]/option[@value="Mongola"]'));
        $payload = $this->person(['nationality' => 'Mongola']);
        $this->putJson(route('beneficiaries.update', $person), $payload)->assertOk()->assertJsonPath('beneficiary.nationality', 'Mongola');
        $this->putJson(route('beneficiaries.update-attention', $person), $this->headers + ['beneficiary' => $payload])
            ->assertOk()->assertJsonPath('beneficiary.nationality', 'Mongola');
        $this->postJson(route('beneficiaries.store'), $this->headers + ['beneficiary' => $payload])
            ->assertUnprocessable()->assertJsonValidationErrors('beneficiary.nationality');
        $this->assertSame('Mongola', $person->fresh()->nationality);
    }

    public function test_associated_indicator_copies_keep_the_selected_nationality(): void
    {
        $principal = IndicadorProyecto::findOrFail($this->headers['indicador_proyecto_id']);
        $indicator = Indicador::create(['codigo' => 'ASSOC', 'descripcion' => 'Asociado', 'unidad_conteo' => 'Personas',
            'espacio_coordinacion' => 'NNA', 'edad_desde' => 0, 'edad_hasta' => 120]);
        $assigned = IndicadorProyecto::create(['proyecto_id' => $principal->proyecto_id,
            'sector_proyecto_id' => $principal->sector_proyecto_id, 'indicador_id' => $indicator->id, 'estatus' => true]);
        $principal->indicadoresAsociados()->attach($assigned);
        $this->postJson(route('beneficiaries.store'), $this->headers + [
            'associated_indicator_ids' => [$assigned->id], 'beneficiary' => $this->person(['nationality' => 'Colombiana']),
        ])->assertCreated()->assertJsonCount(1, 'associated_reports');
        $this->assertSame(['Colombiana', 'Colombiana'], Beneficiary::orderBy('id')->pluck('nationality')->all());
        $this->assertDatabaseCount('report_indicator_copies', 1);
    }

    public function test_migration_preserves_historical_records_without_assuming_their_nationality(): void
    {
        $person = $this->savePerson();
        $before = $person->getRawOriginal();
        unset($before['nationality']);
        // This runs only in the guarded SQLite :memory: database.
        $migration = require database_path('migrations/2026_10_27_120000_add_nationality_to_beneficiaries_table.php');
        $migration->down();
        $migration->up();
        $after = (array) DB::table('beneficiaries')->find($person->id);
        $this->assertNull($after['nationality']);
        unset($after['nationality']);
        $this->assertEquals($before, $after);
        $this->assertDatabaseCount('beneficiaries', 1);
    }
}
