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

class BeneficiaryBirthDateTest extends TestCase
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
        return ['has_informed_consent' => true, 'full_name' => 'María González', 'birth_date' => today()->subYears(34)->toDateString(), 'age' => 34, 'sex' => 'Mujer',
            'disability' => 'Ninguna', 'ethnicity' => 'Ninguna', 'pregnant_lactating' => 'Ninguna', 'is_recurrent' => false];
    }

    public function test_birth_date_is_required_and_invalid_dates_are_rejected_on_both_creation_paths(): void
    {
        foreach ([null, '', '2030-01-01', '2020-02-30', 'not-a-date', ['invalid']] as $birth) {
            $person = array_replace($this->person(), ['birth_date' => $birth]);
            $this->postJson(route('beneficiaries.store'), $this->headers + ['beneficiary' => $person])
                ->assertUnprocessable()->assertJsonValidationErrors('beneficiary.birth_date');
            $this->postJson(route('reports.store'), $this->headers + ['beneficiaries' => [$person]])
                ->assertUnprocessable()->assertJsonValidationErrors('beneficiaries.0.birth_date');
        }
        $this->assertDatabaseCount('beneficiaries', 0);
    }

    public function test_age_is_calculated_at_attention_date_and_client_age_is_ignored(): void
    {
        foreach ([['2000-06-14', 25], ['2000-06-15', 25], ['2000-06-16', 24], ['2025-06-15', 0]] as [$birth, $age]) {
            $headers = array_replace($this->headers, ['report_date' => '2025-06-15']);
            $this->postJson(route('beneficiaries.store'), $headers + ['beneficiary' => array_replace($this->person(), ['birth_date' => $birth, 'age' => 99])])
                ->assertCreated()->assertJsonPath('beneficiary.age', $age)->assertJsonPath('beneficiary.birth_date', $birth);
        }
        $this->assertDatabaseCount('beneficiaries', 4);
    }

    public function test_lookup_returns_stored_birth_date_and_keeps_legacy_birth_dates_unknown(): void
    {
        $this->postJson(route('beneficiaries.store'), $this->headers + ['beneficiary' => $this->person() + ['national_id' => 'V12345678']])->assertCreated();
        $this->postJson(route('beneficiaries.lookup'), ['national_id' => 'V12345678'])->assertOk()
            ->assertJsonPath('beneficiary.birth_date', $this->person()['birth_date']);
        Beneficiary::firstOrFail()->update(['birth_date' => null]);
        $this->postJson(route('beneficiaries.lookup'), ['national_id' => 'V12345678'])->assertOk()
            ->assertJsonPath('beneficiary.birth_date', null);
    }

    public function test_changing_attention_date_recalculates_ages_and_rejects_dates_before_birth(): void
    {
        $headers = array_replace($this->headers, ['report_date' => '2025-06-15']);
        $response = $this->postJson(route('beneficiaries.store'), $headers + ['beneficiary' => array_replace($this->person(), ['birth_date' => '2000-06-16'])])->assertCreated();
        $report = Report::findOrFail($response->json('report.id'));
        $this->putJson(route('reports.update', $report), array_replace($headers, ['report_date' => '2025-06-16']))->assertOk();
        $this->assertEquals(25, $report->beneficiaries()->firstOrFail()->age);
        $this->putJson(route('reports.update', $report), array_replace($headers, ['report_date' => '1999-01-01']))
            ->assertUnprocessable()->assertJsonValidationErrors('report_date');
    }

    public function test_batch_creation_and_editing_recalculate_age_and_require_birth_date(): void
    {
        $headers = array_replace($this->headers, ['report_date' => '2025-06-15']);
        $person = array_replace($this->person(), ['birth_date' => '2000-06-16', 'age' => 99]);
        $this->post(route('reports.store'), $headers + ['beneficiaries' => [$person]])->assertSessionHasNoErrors()->assertRedirect();
        $beneficiary = Beneficiary::firstOrFail();
        $this->assertEquals(24, $beneficiary->age);
        $this->putJson(route('beneficiaries.update', $beneficiary), array_replace($person, ['birth_date' => '2000-06-15']))
            ->assertOk()->assertJsonPath('beneficiary.age', 25);
        unset($person['birth_date']);
        $this->putJson(route('beneficiaries.update', $beneficiary), $person)
            ->assertUnprocessable()->assertJsonValidationErrors('birth_date');
        $this->get(route('reports.create'))->assertOk()->assertSee('id="beneficiary_birth_date" required', false)
            ->assertSee('readonly aria-readonly="true"', false);
    }
}
