<?php

namespace Tests\Feature;

use App\Models\Municipality;
use App\Models\Parish;
use App\Models\Report;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GeneralReportDatesTest extends TestCase
{
    use RefreshDatabase;

    private User $reporter;
    private array $geography;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reporter = User::factory()->create(['role' => 'reporter']);
        $this->actingAs($this->reporter);
        $state = State::create(['code' => 'A', 'name' => 'Estado']);
        $municipality = Municipality::create(['state_id' => $state->id, 'code' => 'A01', 'name' => 'Municipio']);
        $parish = Parish::create(['municipality_id' => $municipality->id, 'code' => 'A0101', 'name' => 'Parroquia']);
        $this->geography = ['state_id' => $state->id, 'municipality_id' => $municipality->id, 'parish_id' => $parish->id];
    }

    private function record(User $user, string $attention, string $registered): void
    {
        $report = Report::create($this->geography + [
            'user_id' => $user->id, 'report_date' => $attention, 'reporter_first_name' => 'Prueba',
            'reporter_last_name' => 'Fechas', 'reporter_email' => $user->email, 'organization' => 'ASONACOP',
            'installation_type' => 'Comunidad / Espacio Comunitario', 'place_name' => 'Lugar',
            'recurrence_status' => 'nuevo', 'total_beneficiaries' => 1, 'beneficiary_breakdown' => [],
        ]);
        $beneficiary = $report->beneficiaries()->create(['full_name' => 'Persona de prueba', 'age' => 10, 'sex' => 'Mujer']);
        $beneficiary->forceFill(['created_at' => $registered])->save();
    }

    public function test_date_inputs_have_separate_bounds_from_accessible_records_only(): void
    {
        $this->record($this->reporter, '2026-08-04', '2026-09-02 23:59:59');
        $this->record($this->reporter, '2026-08-20', '2026-09-10 00:01:00');
        $other = User::factory()->create(['role' => 'reporter']);
        $this->record($other, '2000-01-01', '2000-01-01 00:00:00');
        $this->record($other, '2030-12-31', '2030-12-31 00:00:00');

        $this->get(route('general-reports.index'))->assertOk()
            ->assertViewHas('dateBounds', [
                'attention' => ['min' => '2026-08-04', 'max' => '2026-08-20'],
                'registered' => ['min' => '2026-09-02', 'max' => '2026-09-10'],
            ])
            ->assertSee('min="2026-08-04" max="2026-08-20"', false)
            ->assertSee('min="2026-09-02" max="2026-09-10"', false)
            ->assertSee('Disponible: 04/08/2026 al 20/08/2026.');
    }

    public function test_bounds_do_not_shrink_with_filters_and_expand_when_new_records_are_saved(): void
    {
        $this->record($this->reporter, '2026-08-04', '2026-09-02 12:00:00');
        $this->record($this->reporter, '2026-08-20', '2026-09-10 12:00:00');
        $this->get(route('general-reports.index', ['attention_from' => '2026-08-10', 'registered_to' => '2026-09-10']))->assertOk()
            ->assertViewHas('summary', fn ($summary) => $summary['beneficiaries'] === 1)
            ->assertViewHas('dateBounds', fn ($bounds) => $bounds['attention']['min'] === '2026-08-04');
        $this->record($this->reporter, '2026-09-21', '2026-09-21 12:00:00');
        $this->get(route('general-reports.index'))->assertOk()
            ->assertViewHas('dateBounds', fn ($bounds) => $bounds['attention']['max'] === '2026-09-21' && $bounds['registered']['max'] === '2026-09-21');
    }

    public function test_server_rejects_dates_outside_the_saved_period_and_inverted_ranges(): void
    {
        $this->record($this->reporter, '2026-08-04', '2026-09-02 12:00:00');
        $this->record($this->reporter, '2026-08-20', '2026-09-10 12:00:00');
        foreach (['attention_from', 'attention_to', 'registered_from', 'registered_to'] as $field) {
            foreach (['2000-01-01', '2030-12-31', 'invalid'] as $value) {
                $this->getJson(route('general-reports.index', [$field => $value]))
                    ->assertUnprocessable()->assertJsonValidationErrors($field);
            }
        }
        foreach (['attention' => ['2026-08-20', '2026-08-04'], 'registered' => ['2026-09-10', '2026-09-02']] as $group => [$from, $to]) {
            $this->getJson(route('general-reports.index', [$group.'_from' => $from, $group.'_to' => $to]))
                ->assertUnprocessable()->assertJsonValidationErrors($group.'_to');
        }
        $this->get(route('general-reports.index', ['attention_from' => '2026-08-04', 'attention_to' => '2026-08-20', 'registered_from' => '2026-09-02', 'registered_to' => '2026-09-10']))
            ->assertOk()->assertViewHas('summary', fn ($summary) => $summary['beneficiaries'] === 2);
    }

    public function test_no_records_disables_all_four_date_inputs(): void
    {
        $response = $this->get(route('general-reports.index'))->assertOk()
            ->assertViewHas('dateBounds', ['attention' => ['min' => null, 'max' => null], 'registered' => ['min' => null, 'max' => null]])
            ->assertSee('Sin fechas registradas disponibles.');
        foreach (['attention_from', 'attention_to', 'registered_from', 'registered_to'] as $field) {
            $this->assertMatchesRegularExpression('/<input[^>]*name="'.$field.'"[^>]*disabled[^>]*>/', $response->getContent());
        }
        $this->getJson(route('general-reports.index', ['attention_from' => '2026-09-21']))
            ->assertUnprocessable()->assertJsonValidationErrors('attention_from');
    }
}
