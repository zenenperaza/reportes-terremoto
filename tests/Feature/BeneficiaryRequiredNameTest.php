<?php

namespace Tests\Feature;

use App\Models\Beneficiary;
use App\Models\Municipality;
use App\Models\Parish;
use App\Models\PlaceName;
use App\Models\Report;
use App\Models\Sector;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BeneficiaryRequiredNameTest extends TestCase
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
        $sector = Sector::create(['name' => 'Sector', 'slug' => 'required-name']);
        PlaceName::create(['name' => 'Lugar de prueba']);
        $this->headers = $this->reportProjectContext($user, $state, $sector) + [
            'report_date' => today()->toDateString(), 'organization' => 'ASONACOP',
            'state_id' => $state->id, 'municipality_id' => $municipality->id, 'parish_id' => $parish->id,
            'installation_type' => 'Comunidad / Espacio Comunitario', 'place_name' => 'Lugar de prueba',
        ];
    }

    private function person(): array
    {
        return ['has_informed_consent' => true, 'full_name' => 'María González', 'age' => 34, 'sex' => 'Mujer',
            'disability' => 'Ninguna', 'ethnicity' => 'Ninguna', 'pregnant_lactating' => 'Ninguna', 'is_recurrent' => false];
    }

    public function test_missing_empty_and_whitespace_names_are_rejected_on_both_creation_paths(): void
    {
        foreach ([null, '', '   ', ['invalid'], str_repeat('N', 151)] as $name) {
            $person = array_replace($this->person(), ['full_name' => $name]);
            $this->postJson(route('beneficiaries.store'), $this->headers + ['beneficiary' => $person])
                ->assertUnprocessable()->assertJsonValidationErrors('beneficiary.full_name');
            $this->postJson(route('reports.store'), $this->headers + ['beneficiaries' => [$person]])
                ->assertUnprocessable()->assertJsonValidationErrors('beneficiaries.0.full_name');
        }
        $person = $this->person();
        unset($person['full_name']);
        $this->postJson(route('beneficiaries.store'), $this->headers + ['beneficiary' => $person])
            ->assertUnprocessable()->assertJsonValidationErrors('beneficiary.full_name');
        $this->postJson(route('reports.store'), $this->headers + ['beneficiaries' => [$person]])
            ->assertUnprocessable()->assertJsonValidationErrors('beneficiaries.0.full_name');
        $this->assertDatabaseCount('reports', 0);
        $this->assertDatabaseCount('beneficiaries', 0);
    }

    public function test_every_person_in_a_batch_requires_a_name_without_partial_saves(): void
    {
        $this->postJson(route('reports.store'), $this->headers + ['beneficiaries' => [
            $this->person(), array_replace($this->person(), ['full_name' => '']),
        ]])->assertUnprocessable()->assertJsonValidationErrors('beneficiaries.1.full_name');
        $this->assertDatabaseCount('reports', 0);
        $this->assertDatabaseCount('beneficiaries', 0);
    }

    public function test_valid_names_are_accepted_and_adding_to_an_existing_report_cannot_skip_the_name(): void
    {
        $response = $this->postJson(route('beneficiaries.store'), $this->headers + ['beneficiary' => $this->person()])
            ->assertCreated();
        $reportId = $response->json('report.id');
        $this->postJson(route('beneficiaries.store'), $this->headers + ['report_id' => $reportId,
            'beneficiary' => array_replace($this->person(), ['full_name' => '']),
        ])->assertUnprocessable()->assertJsonValidationErrors('beneficiary.full_name');
        $this->assertDatabaseCount('beneficiaries', 1);
        $this->assertDatabaseHas('beneficiaries', ['full_name' => 'MARÍA GONZÁLEZ']);
        $this->post(route('reports.store'), $this->headers + ['beneficiaries' => [$this->person()]])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseCount('beneficiaries', 2);
    }

    public function test_required_field_renders_without_backfilling_historical_unknown_names(): void
    {
        $this->postJson(route('beneficiaries.store'), $this->headers + ['beneficiary' => $this->person()])->assertCreated();
        $beneficiary = Beneficiary::firstOrFail();
        $beneficiary->update(['full_name' => null]);
        $this->get(route('reports.create'))->assertOk()->assertSee('Nombre y apellido *')
            ->assertSee('id="beneficiary_full_name"', false)->assertSee('required maxlength="150"', false);
        $this->get(route('reports.show', $beneficiary->report_id))->assertOk();
        $this->assertNull($beneficiary->fresh()->full_name);
        $this->assertSame(1, Report::count());
    }
}
