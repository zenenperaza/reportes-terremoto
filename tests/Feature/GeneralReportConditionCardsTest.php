<?php

namespace Tests\Feature;

use App\Models\{IndicadorProyecto, Municipality, Parish, Report, Sector, State, User, UserGroup};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GeneralReportConditionCardsTest extends TestCase
{
    use RefreshDatabase;
    use \Tests\Concerns\CreatesReportProjectContext;

    private User $owner;
    private array $reports = [];
    private array $assignments = [];

    protected function setUp(): void
    {
        parent::setUp();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->owner = User::factory()->create(['role' => 'reporter']);
        $outsider = User::factory()->create(['role' => 'reporter']);
        $this->actingAs($admin);
        foreach (['A' => $this->owner, 'B' => $outsider] as $code => $owner) {
            $state = State::create(['code' => $code, 'name' => 'Estado '.$code]);
            $municipality = Municipality::create(['state_id' => $state->id, 'code' => $code.'01', 'name' => 'Municipio '.$code]);
            $parish = Parish::create(['municipality_id' => $municipality->id, 'code' => $code.'0101', 'name' => 'Parroquia '.$code]);
            $sector = Sector::create(['name' => 'Sector '.$code, 'slug' => 'condition-'.strtolower($code)]);
            $context = $this->reportProjectContext($owner, $state, $sector);
            $this->assignments[$code] = IndicadorProyecto::findOrFail($context['indicador_proyecto_id']);
            if ($code === 'B') $this->assignments[$code]->indicador->update(['excluir_reporte_beneficiarios' => true]);
            $this->reports[$code] = Report::create($context + ['user_id' => $owner->id, 'report_date' => $code === 'A' ? '2026-10-10' : '2026-10-20',
                'reporting_period' => $code === 'A' ? '2026-10' : '2026-09',
                'reporter_first_name' => 'Prueba', 'reporter_last_name' => '', 'reporter_email' => $owner->email,
                'organization' => 'ASONACOP', 'state_id' => $state->id, 'municipality_id' => $municipality->id,
                'parish_id' => $parish->id, 'place_name' => 'Lugar '.$code, 'installation_type' => 'Comunidad / Espacio Comunitario',
                'recurrence_status' => 'no_recurrente', 'total_beneficiaries' => $code === 'A' ? 4 : 1, 'beneficiary_breakdown' => []]);
        }
        foreach ([
            [30, 'Mujer', 'Discapacidad Visual', 'Sí', true, false, 'Wayúu'],
            [17, 'Mujer', 'Ninguna', 'Sí', false, false, 'Ninguna'],
            [50, 'Hombre', 'Discapacidad Física o Motora', 'No', false, true, null],
            [0, 'Hombre', null, null, false, false, ''],
        ] as [$age, $sex, $disability, $pregnancy, $reported, $recurrent, $ethnicity]) {
            $this->reports['A']->beneficiaries()->create(['full_name' => 'Persona de prueba', 'age' => $age, 'sex' => $sex,
                'disability' => $disability, 'ethnicity' => $ethnicity, 'pregnant_lactating' => $pregnancy, 'is_recurrent' => $recurrent])
                ->forceFill(['reported_at' => $reported ? '2026-10-21' : null])->save();
        }
        $this->reports['B']->beneficiaries()->create(['full_name' => 'Persona de otro equipo', 'age' => 40, 'sex' => 'Mujer',
            'disability' => 'Discapacidad Auditiva', 'ethnicity' => 'Warao', 'pregnant_lactating' => 'Sí', 'is_recurrent' => false])
            ->forceFill(['reported_at' => '2026-10-21'])->save();
    }

    private function report(array $filters, int $people, int $disability, int $pregnancy)
    {
        return $this->get(route('general-reports.index', $filters))->assertOk()
            ->assertViewHas('summary', fn ($summary) => $summary['beneficiaries'] === $people && $summary['disability'] === $disability && $summary['pregnancy'] === $pregnancy);
    }

    public function test_three_condition_cards_use_filtered_individual_data_without_changing_existing_totals(): void
    {
        $response = $this->report([], 5, 3, 3)->assertSee('Personas con discapacidad')->assertSee('Embarazadas o lactantes');
        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument;
        $dom->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        libxml_clear_errors(); libxml_use_internal_errors($previous);
        $xpath = new \DOMXPath($dom);
        $this->assertCount(8, $xpath->query('//*[@id="general-summary-cards"]/div[contains(@class,"general-kpi-column")]/article[contains(@class,"general-kpi-card")]'));
        $this->assertCount(3, $xpath->query('//*[@id="general-summary-cards"]//article[@data-condition-card]'));
        $this->assertSame('2', $xpath->query('//*[@data-condition-card="ethnicity"]//strong')->item(0)->textContent);
        foreach (['disability', 'pregnancy'] as $key) {
            $this->assertSame('3', $xpath->query('//*[@data-condition-card="'.$key.'"]//strong')->item(0)->textContent);
        }
        $this->assertDatabaseCount('beneficiaries', 5);
        $this->assertDatabaseCount('reports', 2);
    }

    public function test_cards_respect_reported_status_sex_age_and_recurrence(): void
    {
        $this->report(['reported' => '1'], 2, 2, 2);
        $this->report(['reported' => '0'], 3, 1, 1);
        $this->report(['sex' => 'Hombre'], 2, 1, 0);
        $this->report(['age_from' => 0, 'age_to' => 17], 2, 0, 1);
        $this->report(['is_recurrent' => '1'], 1, 1, 0);
    }

    public function test_cards_respect_period_attention_dates_location_and_indicator_without_beneficiary_exclusions(): void
    {
        $this->report(['reporting_period' => ['2026-09']], 1, 1, 1);
        $this->report(['attention_from' => '2026-10-19', 'attention_to' => '2026-10-20'], 1, 1, 1);
        $this->report(['state_id' => [$this->reports['A']->state_id]], 4, 2, 2);
        $this->report(['indicador_id' => [$this->assignments['B']->indicador_id]], 1, 1, 1);
    }

    public function test_reporter_and_coordinator_cards_never_include_outsider_records(): void
    {
        $this->actingAs($this->owner);
        $this->report([], 4, 2, 2);
        $group = UserGroup::create(['name' => 'Equipo', 'is_active' => true]);
        $coordinator = User::factory()->create(['role' => 'coordinator']);
        $group->users()->attach([$this->owner->id, $coordinator->id]);
        $this->actingAs($coordinator);
        $this->report([], 4, 2, 2);
    }

    public function test_empty_results_show_zero_cards_and_no_separate_pregnancy_or_lactation_counts_are_invented(): void
    {
        $this->report(['age_from' => 90, 'age_to' => 100], 0, 0, 0)
            ->assertSee('data-condition-card="disability"', false)->assertSee('data-condition-card="pregnancy"', false)
            ->assertViewHas('summary', fn ($s) => $s['ethnicity'] === 0)->assertSee('Sin resultados');
        $this->get(route('indicator-reports.index'))->assertOk()->assertDontSee('data-condition-card=', false);
    }

    public function test_ethnicity_card_excludes_unspecified_values_and_respects_project_status_location_and_team(): void
    {
        foreach ([
            [[], 2],
            [['reported' => '1'], 2],
            [['reported' => '0'], 0],
            [['sex' => 'Hombre'], 0],
            [['project_ids' => [$this->reports['A']->proyecto_id]], 1],
            [['state_id' => [$this->reports['B']->state_id]], 1],
            [['reporting_period' => ['2026-09']], 1],
        ] as [$filters, $expected]) {
            $this->get(route('general-reports.index', $filters))->assertOk()->assertSee('Personas indígenas / etnia')
                ->assertViewHas('summary', fn ($s) => $s['ethnicity'] === $expected);
        }
        $this->actingAs($this->owner)->get(route('general-reports.index'))->assertOk()
            ->assertViewHas('summary', fn ($s) => $s['ethnicity'] === 1);
    }
}
