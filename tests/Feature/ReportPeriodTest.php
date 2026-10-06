<?php

namespace Tests\Feature;

use App\Models\{Activity, Municipality, Parish, PlaceName, Report, Sector, State, SystemSetting, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class ReportPeriodTest extends TestCase
{
    use RefreshDatabase;

    private function period(string $value): void
    {
        SystemSetting::updateOrCreate(['key' => SystemSetting::CURRENT_PERIOD], ['value' => $value]);
    }

    private function payload(): array
    {
        $state = State::create(['code' => 'VE01', 'name' => 'Estado']);
        $municipality = Municipality::create(['state_id' => $state->id, 'code' => 'VE0101', 'name' => 'Municipio']);
        $parish = Parish::create(['municipality_id' => $municipality->id, 'code' => 'VE010101', 'name' => 'Parroquia']);
        $sector = Sector::create(['name' => 'Sector', 'slug' => 'sector', 'sort_order' => 1]);
        $activity = Activity::create(['sector_id' => $sector->id, 'code' => 'ACT-01', 'title' => 'Actividad', 'sort_order' => 1]);
        PlaceName::create(['name' => 'Lugar de prueba']);
        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true, 'can_mark_reported' => true]));

        return ['report_date' => today()->toDateString(), 'organization' => 'ASONACOP', 'state_id' => $state->id,
            'municipality_id' => $municipality->id, 'parish_id' => $parish->id, 'installation_type' => 'Comunidad / Espacio Comunitario',
            'place_name' => 'Lugar de prueba', 'sector_id' => $sector->id, 'activity_id' => $activity->id];
    }

    private function person(): array
    {
        return ['has_informed_consent' => true, 'full_name' => 'Persona de prueba', 'age' => 10, 'sex' => 'Mujer',
            'disability' => 'Ninguna', 'ethnicity' => 'Ninguna', 'pregnant_lactating' => 'N/A', 'is_recurrent' => false];
    }

    public function test_form_and_both_creation_paths_use_configured_period_not_attention_date_or_forged_input(): void
    {
        $payload = $this->payload();
        $this->period('2031-12');
        $this->get(route('reports.create'))->assertOk()->assertSee('value="Diciembre 2031" readonly', false)
            ->assertSee('name="period_snapshot" value="2031-12"', false);
        $this->post(route('reports.store'), $payload + ['beneficiaries' => [$this->person()], 'reporting_period' => '2020-01', 'period_snapshot' => '2031-12'])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->postJson(route('beneficiaries.store'), $payload + ['beneficiary' => $this->person(), 'reporting_period' => '2020-01', 'period_snapshot' => '2031-12'])->assertCreated();
        $this->assertDatabaseCount('reports', 2);
        $this->assertSame(['2031-12'], Report::pluck('reporting_period')->unique()->values()->all());
        $this->assertSame(today()->toDateString(), Report::first()->report_date->format('Y-m-d'));
    }

    public function test_stale_forms_are_rejected_and_existing_groups_keep_their_period_after_setting_changes(): void
    {
        $payload = $this->payload();
        $this->period('2026-09');
        $this->postJson(route('beneficiaries.store'), $payload + ['beneficiary' => $this->person(), 'period_snapshot' => '2026-09'])->assertCreated();
        $report = Report::first();
        $this->period('2026-10');
        $this->postJson(route('beneficiaries.store'), $payload + ['beneficiary' => $this->person(), 'period_snapshot' => '2026-09'])
            ->assertUnprocessable()->assertJsonValidationErrors('period_snapshot');
        $this->postJson(route('reports.store'), $payload + ['beneficiaries' => [$this->person()], 'period_snapshot' => '2026-09'])
            ->assertUnprocessable()->assertJsonValidationErrors('period_snapshot');
        $this->postJson(route('beneficiaries.store'), $payload + ['report_id' => $report->id, 'beneficiary' => $this->person(), 'period_snapshot' => '2026-09'])
            ->assertOk();
        $this->putJson(route('reports.update', $report), $payload + ['reporting_period' => '2026-10'])->assertOk();
        $this->assertSame('2026-09', $report->refresh()->reporting_period);
        $this->assertSame(2, $report->beneficiaries()->count());
        $this->get(route('reports.edit', $report))->assertOk()->assertSee('value="Septiembre 2026" readonly', false);
    }

    public function test_period_filters_work_in_records_all_reports_and_exports_and_keep_old_records_distinct(): void
    {
        $payload = $this->payload();
        foreach (['2026-09', '2026-10', null] as $period) {
            $this->period($period ?? '2026-09');
            $this->postJson(route('beneficiaries.store'), $payload + ['beneficiary' => $this->person()])->assertCreated();
            if (!$period) Report::latest('id')->first()->update(['reporting_period' => null]);
        }
        $this->get(route('reports.index', ['reporting_period' => '2026-09', 'draw' => 1]))->assertOk()->assertJsonPath('recordsFiltered', 1);
        foreach (['2026-09', '2026-10', 'unassigned'] as $period) {
            $filters = ['reporting_period' => $period, 'reported' => ''];
            $this->get(route('beneficiaries.summary', $filters))->assertOk()->assertViewHas('summary', fn ($s) => $s['total'] === 1);
            foreach (['general-reports.index', 'indicator-reports.index'] as $route) {
                $this->get(route($route, $filters))->assertOk()->assertViewHas('summary', fn ($s) => $s['beneficiaries'] === 1);
            }
        }
        $response = $this->get(route('reports.export', ['reporting_period' => '2026-09']))->assertOk();
        $this->assertCount(2, array_filter(explode("\n", trim($response->streamedContent()))));
        $response = $this->get(route('beneficiaries.export', ['reporting_period' => '2026-10']))->assertOk();
        $path = tempnam(sys_get_temp_dir(), 'period-export-');
        try {
            file_put_contents($path, $response->streamedContent());
            $book = IOFactory::load($path);
            $this->assertSame(2, $book->getActiveSheet()->getHighestDataRow());
            $book->disconnectWorksheets();
        } finally {
            unlink($path);
        }
        $this->post(route('beneficiaries.mark-reported'), ['reporting_period' => '2026-09', 'reported_at' => today()->toDateString()])->assertRedirect();
        $this->assertSame(1, \App\Models\Beneficiary::whereNotNull('reported_at')->count());
        $this->assertSame(1, Report::whereNull('reporting_period')->count());
    }

    public function test_period_filters_are_validated_and_options_respect_record_visibility(): void
    {
        $payload = $this->payload();
        $this->period('2031-12');
        $this->postJson(route('beneficiaries.store'), $payload + ['beneficiary' => $this->person()])->assertCreated();
        foreach (['reports.index', 'reports.export', 'beneficiaries.summary', 'beneficiaries.export', 'general-reports.index', 'indicator-reports.index'] as $route) {
            $this->getJson(route($route, ['reporting_period' => '2026-13']))->assertUnprocessable()->assertJsonValidationErrors('reporting_period');
        }
        $this->actingAs(User::factory()->create(['role' => 'reporter', 'is_active' => true]));
        foreach (['reports.index', 'beneficiaries.summary', 'general-reports.index', 'indicator-reports.index'] as $route) {
            $this->get(route($route))->assertOk()->assertViewHas('periodOptions', fn ($options) =>
                $route === 'reports.index' ? $options->isEmpty() : $options->keys()->all() === ['2031-12']);
        }
    }

    public function test_multiple_periods_combine_results_and_exports_without_bypassing_closed_periods(): void
    {
        $payload = $this->payload();
        foreach (['2026-08', '2026-09', '2026-10', null] as $period) {
            $this->period($period ?? '2026-09');
            $this->postJson(route('beneficiaries.store'), $payload + ['beneficiary' => $this->person()])->assertCreated();
            if ($period === null) Report::latest('id')->first()->update(['reporting_period' => null]);
        }
        foreach ([
            [['2026-08', '2026-09'], 2], [['2026-09', 'unassigned'], 2],
            [['unassigned'], 1], [[''], 4], [['', '2026-09', '2026-09'], 1],
        ] as [$periods, $expected]) {
            $filters = ['reporting_period' => $periods];
            $this->getJson(route('reports.index', $filters + ['draw' => 1]))->assertOk()->assertJsonPath('recordsFiltered', $expected);
            $this->get(route('beneficiaries.summary', $filters))->assertOk()->assertViewHas('summary', fn ($s) => $s['total'] === $expected);
            foreach (['general-reports.index', 'indicator-reports.index'] as $route) {
                $this->get(route($route, $filters))->assertOk()->assertViewHas('summary', fn ($s) => $s['beneficiaries'] === $expected);
            }
        }
        $filters = ['reporting_period' => ['2026-08', '2026-09', 'unassigned']];
        $response = $this->get(route('beneficiaries.summary', $filters))->assertOk()
            ->assertSee('name="reporting_period[]" id="reporting-period" multiple', false)
            ->assertSee('value="2026-08" selected', false)->assertSee('value="2026-09" selected', false)
            ->assertSee('value="unassigned" selected', false);
        $dom = new \DOMDocument;
        @$dom->loadHTML($response->getContent());
        $xpath = new \DOMXPath($dom);
        $this->assertSame(3, $xpath->query('//form[@id="beneficiary-reported-filter"]//input[@name="reporting_period[]"]')->length);
        $this->assertSame(3, $xpath->query('//form[contains(@action,"marcar-reportados")]//input[@name="reporting_period[]"]')->length);
        $csv = $this->get(route('reports.export', $filters))->assertOk();
        $this->assertCount(4, array_filter(explode("\n", trim($csv->streamedContent()))));
        $excel = $this->get(route('beneficiaries.export', $filters))->assertOk();
        $path = tempnam(sys_get_temp_dir(), 'multi-period-');
        try {
            file_put_contents($path, $excel->streamedContent());
            $book = IOFactory::load($path);
            $this->assertSame(4, $book->getActiveSheet()->getHighestDataRow());
            $book->disconnectWorksheets();
        } finally { unlink($path); }

        $this->put(route('system-configuration.periods.update', '2026-08'), ['is_closed' => 1])->assertRedirect();
        $this->postJson(route('beneficiaries.mark-reported'), $filters + ['reported_at' => today()->toDateString()])->assertStatus(409);
        $this->assertSame(0, \App\Models\Beneficiary::whereNotNull('reported_at')->count());
        $open = ['reporting_period' => ['2026-09', '2026-10'], 'reported_at' => today()->toDateString()];
        $this->post(route('beneficiaries.mark-reported'), $open)->assertRedirect();
        $this->assertSame(2, \App\Models\Beneficiary::whereNotNull('reported_at')->count());
        $this->actingAs(User::factory()->create(['role' => 'reporter', 'is_active' => true]));
        foreach (['beneficiaries.summary', 'general-reports.index', 'indicator-reports.index'] as $route) {
            $key = $route === 'beneficiaries.summary' ? 'total' : 'beneficiaries';
            $this->get(route($route, $filters + ['reported' => '']))->assertOk()->assertViewHas('summary', fn ($s) => $s[$key] === 0);
        }
    }

    public function test_invalid_multiple_periods_are_rejected_before_queries(): void
    {
        $this->payload();
        foreach (['reports.index', 'reports.export', 'beneficiaries.summary', 'beneficiaries.export', 'general-reports.index', 'indicator-reports.index'] as $route) {
            foreach ([['2026-09', 'invalid'], [['2026-09']], ['wrong_key' => '2026-09']] as $invalid) {
                $this->getJson(route($route, ['reporting_period' => $invalid]))->assertUnprocessable()->assertJsonValidationErrors('reporting_period');
            }
        }
        $this->getJson(route('beneficiaries.summary', ['reporting_period' => array_fill(0, 241, '2026-09')]))
            ->assertUnprocessable()->assertJsonValidationErrors('reporting_period');
    }

    public function test_period_history_keeps_empty_previous_periods_and_only_admins_can_close_or_reopen(): void
    {
        $this->payload();
        $this->period('2026-08');
        $this->put(route('system-configuration.update'), ['period_month' => 9, 'period_year' => 2026])->assertRedirect();
        $this->get(route('system-configuration.index'))->assertOk()
            ->assertSee('Agosto 2026')->assertSee('Septiembre 2026')->assertSee('Administrador de períodos')
            ->assertSee('id="closed-2026-08"', false);
        $url = route('system-configuration.periods.update', '2026-08');
        $this->put($url, ['is_closed' => '1'])->assertRedirect();
        $this->assertDatabaseHas('reporting_periods', ['period' => '2026-08', 'is_closed' => true]);
        $this->assertNotNull(\App\Models\ReportingPeriod::where('period', '2026-08')->first()->closed_at);
        $this->putJson($url, ['is_closed' => 'invalid'])->assertUnprocessable();
        $this->putJson(route('system-configuration.periods.update', '2025-01'), ['is_closed' => '1'])->assertNotFound();
        $this->putJson(route('system-configuration.update'), ['period_month' => 8, 'period_year' => 2026])->assertStatus(409);
        $this->assertSame('2026-09', \App\Support\ReportPeriod::current());
        $this->put($url, ['is_closed' => '0'])->assertRedirect();
        $this->assertDatabaseHas('reporting_periods', ['period' => '2026-08', 'is_closed' => false, 'closed_at' => null]);
        foreach (['reporter', 'coordinator'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role, 'is_active' => true]));
            $this->putJson($url, ['is_closed' => '1'])->assertForbidden();
        }
        $this->assertDatabaseHas('reporting_periods', ['period' => '2026-08', 'is_closed' => false]);
    }

    public function test_closed_period_blocks_all_record_and_beneficiary_mutations_including_stale_forms(): void
    {
        $payload = $this->payload();
        $this->period('2026-08');
        $this->postJson(route('beneficiaries.store'), $payload + ['beneficiary' => $this->person()])->assertCreated();
        $report = Report::first();
        $beneficiary = $report->beneficiaries()->first();
        $beforeReport = $report->toArray();
        $beforePerson = $beneficiary->toArray();
        $this->get(route('reports.edit', $report))->assertOk();
        $this->put(route('system-configuration.periods.update', '2026-08'), ['is_closed' => '1'])->assertRedirect();
        $this->period('2026-09');
        $this->get(route('reports.show', $report))->assertOk()->assertSee('Período cerrado')
            ->assertViewHas('canEditReport', false)->assertViewHas('canDeleteReport', false)
            ->assertViewHas('canEditBeneficiaries', false)->assertViewHas('canDeleteBeneficiaries', false);
        $this->get(route('reports.edit', $report))->assertStatus(409);
        $this->get(route('reports.edit', ['report' => $report, 'beneficiary' => $beneficiary->id]))->assertStatus(409);
        $this->putJson(route('reports.update', $report), $payload)->assertStatus(409);
        $this->deleteJson(route('reports.destroy', $report))->assertStatus(409);
        $this->putJson(route('beneficiaries.update', $beneficiary), $this->person())->assertStatus(409);
        $this->putJson(route('beneficiaries.update-attention', $beneficiary), $payload + ['beneficiary' => $this->person()])->assertStatus(409);
        $this->deleteJson(route('beneficiaries.destroy', $beneficiary))->assertStatus(409);
        $this->postJson(route('beneficiaries.store'), $payload + ['report_id' => $report->id, 'beneficiary' => $this->person()])->assertStatus(409);
        $this->postJson(route('reports.review', $report))->assertStatus(409);
        $this->assertSame($beforeReport, $report->fresh()->toArray());
        $this->assertSame($beforePerson, $beneficiary->fresh()->toArray());
        $this->assertDatabaseCount('reports', 1);
        $this->assertDatabaseCount('beneficiaries', 1);
        $this->get(route('beneficiaries.summary', ['reporting_period' => '2026-08']))->assertOk()
            ->assertViewHas('hasClosedPendingPeriods', true)
            ->assertDontSee('action="'.route('beneficiaries.mark-reported').'"', false);
        $this->get(route('beneficiaries.export', ['reporting_period' => '2026-08']))->assertOk();
        $this->put(route('system-configuration.periods.update', '2026-08'), ['is_closed' => '0'])->assertRedirect();
        $this->putJson(route('beneficiaries.update', $beneficiary), array_merge($this->person(), ['full_name' => 'Persona corregida']))->assertOk();
        $this->assertSame('PERSONA CORREGIDA', $beneficiary->fresh()->full_name);
    }

    public function test_closed_current_period_rejects_new_records_and_bulk_reporting_is_all_or_nothing(): void
    {
        $payload = $this->payload();
        foreach (['2026-08', '2026-09'] as $period) {
            $this->period($period);
            $this->postJson(route('beneficiaries.store'), $payload + ['beneficiary' => $this->person()])->assertCreated();
        }
        $this->put(route('system-configuration.periods.update', '2026-08'), ['is_closed' => 1])->assertRedirect();
        $this->postJson(route('beneficiaries.mark-reported'), ['reporting_period' => '', 'reported_at' => today()->toDateString()])->assertStatus(409);
        $this->assertSame(0, \App\Models\Beneficiary::whereNotNull('reported_at')->count());
        $this->post(route('beneficiaries.mark-reported'), ['reporting_period' => '2026-09', 'reported_at' => today()->toDateString()])->assertRedirect();
        $this->assertSame(1, \App\Models\Beneficiary::whereNotNull('reported_at')->count());
        $this->put(route('system-configuration.periods.update', '2026-09'), ['is_closed' => 1])->assertRedirect();
        $this->get(route('reports.create'))->assertStatus(409);
        $this->postJson(route('beneficiaries.store'), $payload + ['beneficiary' => $this->person()])->assertStatus(409);
        $this->postJson(route('reports.store'), $payload + ['beneficiaries' => [$this->person()]])->assertStatus(409);
        $this->assertDatabaseCount('reports', 2);
        $this->assertDatabaseCount('beneficiaries', 2);
        // Closing a calendar month never silently assigns that month to legacy data.
        $legacy = Report::first();
        $legacy->update(['reporting_period' => null]);
        $this->get(route('reports.edit', $legacy))->assertOk();
        $this->get(route('system-configuration.index'))->assertOk()->assertViewHas('unassignedCount', 1);
    }

    public function test_reports_default_to_all_periods_and_allow_explicit_periods_or_unassigned(): void
    {
        $payload = $this->payload();
        foreach (['2031-12', '2031-11', null] as $period) {
            $this->period($period ?? '2031-12');
            $this->postJson(route('beneficiaries.store'), $payload + ['beneficiary' => $this->person()])->assertCreated();
            if ($period === null) Report::latest('id')->first()->update(['reporting_period' => null]);
        }

        foreach (['beneficiaries.summary', 'general-reports.index', 'indicator-reports.index'] as $route) {
            $countKey = $route === 'beneficiaries.summary' ? 'total' : 'beneficiaries';
            $response = $this->get(route($route))->assertOk()
                ->assertViewHas('filters', fn ($filters) => $filters['reporting_period'] === '')
                ->assertViewHas('summary', fn ($summary) => $summary[$countKey] === 3)
                ->assertDontSee('value="2031-12" selected', false);
            $response->assertSeeInOrder($route === 'beneficiaries.summary'
                ? ['id="summary_reported"', 'report-period-row', 'id="reporting-period"', 'name="from"']
                : ['name="reported"', 'col-12 report-period-row', 'id="reporting-period"', 'name="attention_from"'], false);

            $this->get(route($route, ['reporting_period' => '']))->assertOk()
                ->assertViewHas('summary', fn ($summary) => $summary[$countKey] === 3);
            $this->get(route($route, ['reporting_period' => 'unassigned']))->assertOk()
                ->assertViewHas('summary', fn ($summary) => $summary[$countKey] === 1);
            $this->period('2032-01');
            $this->get(route($route))->assertOk()
                ->assertDontSee('value="2032-01" selected', false)
                ->assertViewHas('summary', fn ($summary) => $summary[$countKey] === 3);
            $this->period('2031-12');
        }

        $this->get(route('beneficiaries.summary', ['reporting_period' => '']))->assertOk()
            ->assertSee('name="reporting_period" value=""', false)
            ->assertSee('reporting_period=', false);
        foreach ([[[], 4], [['reporting_period' => ''], 4]] as [$filters, $rows]) {
            $response = $this->get(route('beneficiaries.export', $filters))->assertOk();
            $path = tempnam(sys_get_temp_dir(), 'period-default-export-');
            try {
                file_put_contents($path, $response->streamedContent());
                $book = IOFactory::load($path);
                $this->assertSame($rows, $book->getActiveSheet()->getHighestDataRow());
                $book->disconnectWorksheets();
            } finally {
                unlink($path);
            }
        }
        $this->post(route('beneficiaries.mark-reported'), ['reporting_period' => '', 'reported_at' => today()->toDateString()])
            ->assertRedirect(route('beneficiaries.summary', ['reported' => '0', 'reporting_period' => '']));
        $this->assertSame(3, \App\Models\Beneficiary::whereNotNull('reported_at')->count());
    }
}
