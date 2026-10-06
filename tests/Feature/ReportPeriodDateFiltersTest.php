<?php

namespace Tests\Feature;

use App\Models\Municipality;
use App\Models\Parish;
use App\Models\Report;
use App\Models\State;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReportPeriodDateFiltersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        SystemSetting::create(['key' => SystemSetting::CURRENT_PERIOD, 'value' => '2026-09']);
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);
        $state = State::create(['code' => 'A', 'name' => 'Estado']);
        $municipality = Municipality::create(['state_id' => $state->id, 'code' => 'A01', 'name' => 'Municipio']);
        $parish = Parish::create(['municipality_id' => $municipality->id, 'code' => 'A0101', 'name' => 'Parroquia']);
        foreach ([['2026-07', '2026-06-01', '2026-07-15'], ['2026-07', '2026-07-27', '2026-08-02'], ['2026-09', '2026-09-20', '2026-10-05'], [null, '2026-06-29', '2026-06-30']] as [$period, $attention, $registered]) {
            $report = Report::create([
                'reporting_period' => $period, 'user_id' => $user->id, 'report_date' => $attention,
                'state_id' => $state->id, 'municipality_id' => $municipality->id, 'parish_id' => $parish->id,
                'reporter_first_name' => 'Prueba', 'reporter_last_name' => 'Períodos', 'reporter_email' => $user->email,
                'organization' => 'ASONACOP', 'installation_type' => 'Comunidad / Espacio Comunitario', 'place_name' => 'Lugar',
                'recurrence_status' => 'nuevo', 'total_beneficiaries' => 1, 'beneficiary_breakdown' => [],
            ]);
            $person = $report->beneficiaries()->create(['full_name' => 'Prueba', 'age' => 10, 'sex' => 'Mujer']);
            $person->forceFill(['created_at' => $registered.' 12:00:00'])->save();
        }
    }

    public static function reports(): array
    {
        return [
            ['beneficiaries.summary', 'beneficiaries.export', 'beneficiaries.dates', ['from', 'to', 'included_from', 'included_to'], 'total'],
            ['general-reports.index', 'general-reports.export', 'general-reports.dates', ['attention_from', 'attention_to', 'registered_from', 'registered_to'], 'beneficiaries'],
            ['indicator-reports.index', 'indicator-reports.export', 'indicator-reports.dates', ['attention_from', 'attention_to', 'registered_from', 'registered_to'], 'beneficiaries'],
        ];
    }

    #[DataProvider('reports')]
    public function test_all_periods_default_uses_actual_dates_and_selected_period_can_span_two_months(string $route, string $export, string $dates, array $fields, string $count): void
    {
        $this->get(route($route, ['reported' => '']))->assertOk()
            ->assertViewHas('filters', fn ($filters) => $filters['reporting_period'] === '')
            ->assertViewHas('summary', fn ($summary) => $summary[$count] === 4)
            ->assertViewHas('dateBounds', [
                'attention' => ['min' => '2026-06-01', 'max' => '2026-09-20'],
                'registered' => ['min' => '2026-06-30', 'max' => '2026-10-05'],
            ])->assertDontSee('value="2026-09" selected', false);

        $expected = [
            'attention' => ['min' => '2026-06-01', 'max' => '2026-07-27'],
            'registered' => ['min' => '2026-07-15', 'max' => '2026-08-02'],
        ];
        $params = ['reported' => '', 'reporting_period' => ['2026-07']];
        $this->get(route($route, $params))->assertOk()
            ->assertViewHas('summary', fn ($summary) => $summary[$count] === 2)
            ->assertViewHas('dateBounds', $expected)
            ->assertSee('min="2026-06-01" max="2026-07-27"', false)
            ->assertSee('min="2026-07-15" max="2026-08-02"', false)
            ->assertSee('report-period-dates.js')->assertSee('flatpickr.min.js')
            ->assertSee('data-date-bounds-url="'.route($dates).'"', false);
        $this->getJson(route($dates, $params))->assertOk()->assertExactJson($expected);
        // A single day's records enable that day only, independently for registration.
        $this->getJson(route($dates, ['reported' => '', 'reporting_period' => ['2026-09']]))->assertOk()->assertExactJson([
            'attention' => ['min' => '2026-09-20', 'max' => '2026-09-20'],
            'registered' => ['min' => '2026-10-05', 'max' => '2026-10-05'],
        ]);
    }

    #[DataProvider('reports')]
    public function test_actual_boundaries_are_validated_in_pages_and_exports_without_calendar_month_restrictions(string $route, string $export, string $dates, array $fields, string $count): void
    {
        $params = ['reported' => '', 'reporting_period' => ['2026-07']];
        foreach ($fields as $index => $field) {
            $valid = $index < 2 ? ['2026-06-01', '2026-07-27'] : ['2026-07-15', '2026-08-02'];
            $invalid = $index < 2 ? ['2026-05-31', '2026-07-28'] : ['2026-07-14', '2026-08-03'];
            foreach ($valid as $date) {
                $this->getJson(route($route, $params + [$field => $date]))->assertOk();
                $this->get(route($export, $params + [$field => $date]))->assertOk()->assertDownload();
            }
            foreach ($invalid as $date) {
                foreach ([$route, $export] as $endpoint) {
                    $this->getJson(route($endpoint, $params + [$field => $date]))->assertUnprocessable()->assertJsonValidationErrors($field);
                }
            }
        }
        $this->get(route($route, $params + [
            $fields[0] => '2026-06-01', $fields[1] => '2026-07-27',
            $fields[2] => '2026-07-15', $fields[3] => '2026-08-02',
        ]))->assertOk()->assertViewHas('summary', fn ($summary) => $summary[$count] === 2);
    }

    #[DataProvider('reports')]
    public function test_multiple_periods_and_unassigned_use_combined_actual_range(string $route, string $export, string $dates, array $fields, string $count): void
    {
        $params = ['reported' => '', 'reporting_period' => ['2026-09', '2026-07']];
        $this->getJson(route($dates, $params))->assertOk()->assertExactJson([
            'attention' => ['min' => '2026-06-01', 'max' => '2026-09-20'],
            'registered' => ['min' => '2026-07-15', 'max' => '2026-10-05'],
        ]);
        // Continuous interval, not a list of synthetic allowed calendar months.
        $this->get(route($route, $params + [$fields[0] => '2026-08-15']))->assertOk()
            ->assertViewHas('summary', fn ($summary) => $summary[$count] === 1);
        $this->getJson(route($dates, ['reported' => '', 'reporting_period' => ['unassigned']]))->assertOk()->assertExactJson([
            'attention' => ['min' => '2026-06-29', 'max' => '2026-06-29'],
            'registered' => ['min' => '2026-06-30', 'max' => '2026-06-30'],
        ]);
        $this->getJson(route($dates, ['reported' => '', 'reporting_period' => ['2026-09', 'unassigned']]))->assertOk()->assertExactJson([
            'attention' => ['min' => '2026-06-29', 'max' => '2026-09-20'],
            'registered' => ['min' => '2026-06-30', 'max' => '2026-10-05'],
        ]);
    }

    #[DataProvider('reports')]
    public function test_empty_period_disables_dates_and_rejects_dates_in_pages_and_exports(string $route, string $export, string $dates, array $fields, string $count): void
    {
        $params = ['reported' => '', 'reporting_period' => ['2028-02']];
        $expected = ['attention' => ['min' => null, 'max' => null], 'registered' => ['min' => null, 'max' => null]];
        $response = $this->get(route($route, $params))->assertOk()
            ->assertViewHas('summary', fn ($summary) => $summary[$count] === 0)
            ->assertViewHas('dateBounds', $expected)->assertSee('Sin fechas registradas disponibles.');
        $this->getJson(route($dates, $params))->assertOk()->assertExactJson($expected);
        foreach ($fields as $field) {
            $this->assertMatchesRegularExpression('/<input[^>]*name="'.$field.'"[^>]*disabled[^>]*>/', $response->getContent());
            foreach ([$route, $export] as $endpoint) {
                $this->getJson(route($endpoint, $params + [$field => '2028-02-29']))->assertUnprocessable()->assertJsonValidationErrors($field);
            }
        }
    }

    #[DataProvider('reports')]
    public function test_bounds_ignore_other_filters_but_follow_status_permissions_and_new_records(string $route, string $export, string $dates, array $fields, string $count): void
    {
        $first = Report::whereDate('report_date', '2026-06-01')->firstOrFail();
        $person = $first->beneficiaries()->firstOrFail();
        $person->forceFill(['reported_at' => '2026-08-01', 'reported' => true])->save();
        $params = ['reported' => '1', 'reporting_period' => ['2026-07'], 'place_name' => 'Does not exist'];
        $this->getJson(route($dates, $params))->assertOk()->assertExactJson([
            'attention' => ['min' => '2026-06-01', 'max' => '2026-06-01'],
            'registered' => ['min' => '2026-07-15', 'max' => '2026-07-15'],
        ]);
        $this->get(route($route, ['reported' => '', 'reporting_period' => ['2026-07'], $fields[0] => '2026-07-10']))->assertOk()
            ->assertViewHas('dateBounds', fn ($bounds) => $bounds['attention']['min'] === '2026-06-01');
        $reporter = User::factory()->create(['role' => 'reporter']);
        $first->update(['user_id' => $reporter->id]);
        $this->actingAs($reporter)->getJson(route($dates, ['reported' => '', 'reporting_period' => ['2026-07']]))->assertOk()->assertExactJson([
            'attention' => ['min' => '2026-06-01', 'max' => '2026-06-01'],
            'registered' => ['min' => '2026-07-15', 'max' => '2026-07-15'],
        ]);
        $first->update(['report_date' => '2026-05-30']);
        $person->forceFill(['created_at' => '2026-08-03 23:59:59'])->save();
        $this->getJson(route($dates, ['reported' => '', 'reporting_period' => ['2026-07']]))->assertOk()->assertJsonPath('attention.min', '2026-05-30')->assertJsonPath('registered.max', '2026-08-03');
    }

    #[DataProvider('reports')]
    public function test_invalid_period_invalid_dates_and_inverted_ranges_are_rejected(string $route, string $export, string $dates, array $fields, string $count): void
    {
        $this->getJson(route($dates, ['reporting_period' => ['2026-99']]))->assertUnprocessable()->assertJsonValidationErrors('reporting_period');
        foreach ($fields as $field) {
            $this->getJson(route($route, ['reported' => '', $field => '2026-09-31']))->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        foreach ([[$fields[0], $fields[1]], [$fields[2], $fields[3]]] as [$from, $to]) {
            $this->getJson(route($route, ['reported' => '', $from => '2026-08-20', $to => '2026-08-10']))->assertUnprocessable()->assertJsonValidationErrors($to);
            foreach ([$route, $export] as $endpoint) {
                foreach (['2026-07-14', '2026-07-15'] as $end) {
                    $response = $this->getJson(route($endpoint, ['reported' => '', $from => '2026-07-15', $to => $end]))
                        ->assertUnprocessable()->assertJsonValidationErrors($to);
                    $this->assertStringContainsString('de '.($from === $fields[0] ? 'atención' : 'registro').' «Desde» debe ser anterior a «Hasta».', $response->json('errors.'.$to.'.0'));
                    $this->assertStringNotContainsString('validation.', $response->getContent());
                }
            }
        }
    }
}
