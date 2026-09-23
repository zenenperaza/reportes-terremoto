<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Donante;
use App\Models\Indicador;
use App\Models\IndicadorProyecto;
use App\Models\Municipality;
use App\Models\Parish;
use App\Models\Proyecto;
use App\Models\Report;
use App\Models\Sector;
use App\Models\SectorProyecto;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class IndicatorBeneficiaryExclusionTest extends TestCase
{
    use RefreshDatabase;

    public function test_indicator_checkbox_is_saved_validated_and_can_be_unchecked(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->get(route('indicadores.create'))->assertOk()->assertSee('Excluir para reportes de beneficiarios');
        $data = $this->indicatorData('CHECK');
        $this->post(route('indicadores.store'), $data)->assertRedirect(route('indicadores.index'));
        $indicator = Indicador::where('codigo', 'CHECK')->firstOrFail();
        $this->assertFalse($indicator->excluir_reporte_beneficiarios);

        $this->put(route('indicadores.update', $indicator), $data + ['excluir_reporte_beneficiarios' => '1'])
            ->assertRedirect(route('indicadores.index'));
        $this->assertTrue($indicator->refresh()->excluir_reporte_beneficiarios);
        $this->get(route('indicadores.edit', $indicator))->assertOk()
            ->assertSee('name="excluir_reporte_beneficiarios" value="1" checked', false);

        $this->put(route('indicadores.update', $indicator), $data + ['excluir_reporte_beneficiarios' => 'invalid'])
            ->assertSessionHasErrors('excluir_reporte_beneficiarios');
        $this->assertTrue($indicator->refresh()->excluir_reporte_beneficiarios);
        $this->put(route('indicadores.update', $indicator), $data + ['excluir_reporte_beneficiarios' => '0'])
            ->assertRedirect(route('indicadores.index'));
        $this->assertFalse($indicator->refresh()->excluir_reporte_beneficiarios);

        $this->post(route('indicadores.store'), $this->indicatorData('CHECK-NEW') + ['excluir_reporte_beneficiarios' => '1'])
            ->assertRedirect(route('indicadores.index'));
        $this->assertTrue(Indicador::where('codigo', 'CHECK-NEW')->firstOrFail()->excluir_reporte_beneficiarios);
    }

    public function test_summary_totals_groups_and_places_exclude_flagged_indicators_but_keep_legacy_reports(): void
    {
        [$admin, $included, $excluded] = $this->reports();
        $response = $this->actingAs($admin)->get(route('beneficiaries.summary'))
            ->assertOk()->assertViewHas('reportCount', 2)->assertViewHas('pendingBeneficiaryCount', 2)
            ->assertViewHas('summary', fn ($summary) => $summary['total'] === 2)
            ->assertViewHas('groupedBeneficiaries', fn ($groups) => $groups->sum('beneficiary_count') === 2)
            ->assertViewHas('places', fn ($places) => ! $places->contains('Lugar excluido'))
            ->assertDontSee('Indicador EXCLUIDO')->assertSee('Indicador INCLUIDO')->assertSee('Actividad anterior');
        $this->assertStringNotContainsString('Lugar excluido', json_encode($response->viewData('summary345w')));

        $this->get(route('beneficiaries.summary', ['indicador_proyecto_id' => $excluded->indicador_proyecto_id]))
            ->assertOk()->assertViewHas('reportCount', 0)->assertViewHas('pendingBeneficiaryCount', 0)
            ->assertViewHas('summary', fn ($summary) => $summary['total'] === 0);
        $this->get(route('beneficiaries.summary', ['indicador_proyecto_id' => $included->indicador_proyecto_id]))
            ->assertOk()->assertViewHas('summary', fn ($summary) => $summary['total'] === 1);

        $excluded->indicadorProyecto->indicador->update(['excluir_reporte_beneficiarios' => false]);
        $this->get(route('beneficiaries.summary'))->assertOk()
            ->assertViewHas('summary', fn ($summary) => $summary['total'] === 3);
    }

    public function test_excel_excludes_flagged_indicators_even_when_requested_directly(): void
    {
        [$admin, $included, $excluded] = $this->reports();
        $this->actingAs($admin);
        foreach ([[], ['indicador_proyecto_id' => $excluded->indicador_proyecto_id]] as $filters) {
            $response = $this->get(route('beneficiaries.export', $filters))->assertOk();
            $path = tempnam(sys_get_temp_dir(), 'indicator-exclusion-');
            try {
                file_put_contents($path, $response->streamedContent());
                $workbook = IOFactory::load($path);
                $sheet = $workbook->getActiveSheet();
                $dataRows = array_filter(array_slice($sheet->toArray(), 1), fn (array $row) => $row[0] !== null);
                $this->assertCount($filters === [] ? 2 : 0, $dataRows);
                $this->assertStringNotContainsString('Indicador EXCLUIDO', json_encode($sheet->toArray()));
                if ($filters === []) {
                    $this->assertStringContainsString('Indicador INCLUIDO', json_encode($sheet->toArray()));
                    $this->assertStringContainsString('Actividad anterior', json_encode($sheet->toArray()));
                }
                $workbook->disconnectWorksheets();
            } finally {
                unlink($path);
            }
        }
    }

    public function test_locations_with_only_excluded_indicators_are_hidden_only_in_beneficiary_reports(): void
    {
        [$admin, $included, $excluded] = $this->reports();
        $state = State::create(['code' => 'EX', 'name' => 'Estado excluido']);
        $municipality = Municipality::create(['state_id' => $state->id, 'code' => 'EX01', 'name' => 'Municipio excluido']);
        $parish = Parish::create(['municipality_id' => $municipality->id, 'code' => 'EX0101', 'name' => 'Parroquia excluida']);
        $excluded->update(['state_id' => $state->id, 'municipality_id' => $municipality->id, 'parish_id' => $parish->id]);
        $this->actingAs($admin);
        $this->get(route('beneficiaries.summary'))->assertOk()
            ->assertViewHas('states', fn ($items) => ! $items->contains('id', $state->id))
            ->assertViewHas('municipalities', fn ($items) => ! $items->contains('id', $municipality->id))
            ->assertViewHas('parishes', fn ($items) => ! $items->contains('id', $parish->id));
        $this->getJson(route('beneficiaries.locations', ['state_id' => $state->id]))->assertOk()
            ->assertJsonCount(0, 'municipalities')->assertJsonCount(0, 'parishes');
        $this->getJson(route('general-reports.locations', ['state_id' => [$state->id]]))->assertOk()
            ->assertJsonPath('municipalities.0.id', $municipality->id)
            ->assertJsonPath('parishes.0.id', $parish->id);
    }

    public function test_mark_reported_cannot_update_excluded_beneficiaries(): void
    {
        [$admin, $included, $excluded, $legacy] = $this->reports();
        $this->actingAs($admin)->post(route('beneficiaries.mark-reported'), [
            'indicador_proyecto_id' => $excluded->indicador_proyecto_id,
            'reported_at' => today()->toDateString(),
        ])->assertRedirect();
        $this->assertNull($excluded->beneficiaries()->first()->reported_at);

        $this->post(route('beneficiaries.mark-reported'), ['reported_at' => today()->toDateString()])->assertRedirect();
        $this->assertNull($excluded->beneficiaries()->first()->reported_at);
        $this->assertNotNull($included->beneficiaries()->first()->reported_at);
        $this->assertNotNull($legacy->beneficiaries()->first()->reported_at);
        $this->assertDatabaseCount('reports', 3);
        $this->assertDatabaseCount('beneficiaries', 3);
    }

    public function test_exclusion_also_applies_to_already_reported_records_and_all_statuses(): void
    {
        [$admin, $included, $excluded] = $this->reports();
        foreach ([$included, $excluded] as $report) {
            $report->beneficiaries()->update(['reported' => true, 'reported_at' => today()->toDateString()]);
        }
        $this->actingAs($admin)->get(route('beneficiaries.summary', ['reported' => '1']))->assertOk()
            ->assertViewHas('summary', fn ($summary) => $summary['total'] === 1)->assertViewHas('reportCount', 1);
        $this->get(route('beneficiaries.summary', ['reported' => '']))->assertOk()
            ->assertViewHas('summary', fn ($summary) => $summary['total'] === 2)->assertViewHas('reportCount', 2);
    }

    public function test_exclusion_keeps_existing_access_controls_and_does_not_hide_activity_records(): void
    {
        [$admin, $included, $excluded] = $this->reports();
        $reporter = User::factory()->create(['role' => 'reporter']);
        $excluded->update(['user_id' => $reporter->id]);
        $this->actingAs($reporter)->get(route('beneficiaries.summary'))->assertOk()
            ->assertViewHas('reportCount', 0)->assertViewHas('summary', fn ($summary) => $summary['total'] === 0);
        $this->get(route('reports.show', $excluded))->assertOk();
        $this->get(route('reports.show', $included))->assertForbidden();
        $this->get(route('beneficiaries.export'))->assertForbidden();
        $this->post(route('beneficiaries.mark-reported'), ['reported_at' => today()->toDateString()])->assertForbidden();
        $this->actingAs($admin)->get(route('general-reports.index'))->assertOk()->assertSee('Indicador EXCLUIDO');
    }

    public function test_indicator_options_match_saved_visible_indicators_and_keep_legacy_separate(): void
    {
        [$admin, $included, $excluded, $legacy] = $this->reports();
        $included->indicadorProyecto->update(['estatus' => false]);
        Activity::create(['sector_id' => $legacy->sector_id, 'code' => 'UNUSED', 'title' => 'Actividad sin registros', 'active' => true]);
        $unused = Indicador::create($this->indicatorData('SIN-REGISTROS'));
        IndicadorProyecto::create(['proyecto_id' => $included->proyecto_id, 'indicador_id' => $unused->id, 'estatus' => true]);

        $this->actingAs($admin)->get(route('beneficiaries.summary'))->assertOk()
            ->assertViewHas('indicatorOptions', function ($options) use ($included, $legacy): bool {
                return $options->count() === 2
                    && $options->contains('value', 'project:'.$included->indicador_proyecto_id)
                    && $options->contains('value', 'legacy:'.$legacy->activity_id);
            })
            ->assertSee('INCLUIDO: Indicador INCLUIDO')
            ->assertSee('Actividad anterior (registro anterior)')
            ->assertDontSee('Actividad sin registros')->assertDontSee('SIN-REGISTROS')->assertDontSee('Indicador EXCLUIDO');

        $reporter = User::factory()->create(['role' => 'reporter']);
        $legacy->update(['user_id' => $reporter->id]);
        $this->actingAs($reporter)->get(route('beneficiaries.summary'))->assertOk()
            ->assertViewHas('indicatorOptions', fn ($options) => $options->count() === 1 && $options->first()['value'] === 'legacy:'.$legacy->activity_id)
            ->assertDontSee('Indicador INCLUIDO');
    }

    public function test_selector_filters_by_project_assignment_not_shared_legacy_activity_and_preserves_links(): void
    {
        [$admin, $included, $excluded, $legacy] = $this->reports();
        $excluded->indicadorProyecto->indicador->update(['excluir_reporte_beneficiarios' => false]);
        $this->actingAs($admin);

        foreach ([$included, $excluded] as $report) {
            $this->get(route('beneficiaries.summary', [
                'indicator_filter' => 'project:'.$report->indicador_proyecto_id,
                'activity_id' => $legacy->activity_id,
            ]))->assertOk()
                ->assertViewHas('reportCount', 1)
                ->assertViewHas('summary', fn ($summary) => $summary['total'] === 1)
                ->assertViewHas('groupedBeneficiaries', fn ($groups) => $groups->count() === 1 && $groups->first()->indicador_proyecto_id === $report->indicador_proyecto_id)
                ->assertSee('value="project:'.$report->indicador_proyecto_id.'" selected', false)
                ->assertSee('indicador_proyecto_id='.$report->indicador_proyecto_id, false);
        }

        $this->get(route('beneficiaries.summary', ['indicator_filter' => 'legacy:'.$legacy->activity_id]))->assertOk()
            ->assertViewHas('reportCount', 1)
            ->assertViewHas('groupedBeneficiaries', fn ($groups) => $groups->count() === 1 && $groups->first()->indicador_proyecto_id === null);
        $this->get(route('beneficiaries.summary', ['indicador_proyecto_id' => $included->indicador_proyecto_id]))->assertOk()
            ->assertSee('value="project:'.$included->indicador_proyecto_id.'" selected', false);
        $this->get(route('beneficiaries.summary', ['indicator_filter' => '', 'indicador_proyecto_id' => $included->indicador_proyecto_id]))->assertOk()
            ->assertViewHas('reportCount', 3);
        $this->getJson(route('beneficiaries.summary', ['indicator_filter' => 'invalid']))->assertUnprocessable()->assertJsonValidationErrors('indicator_filter.0');
        $this->getJson(route('beneficiaries.summary', ['indicator_filter' => 'project:999999']))->assertUnprocessable()->assertJsonValidationErrors('indicator_filter.0');
    }

    public function test_sector_filter_and_options_use_indicator_sector_even_when_report_sector_is_stale(): void
    {
        [$admin, $included, $excluded, $legacy] = $this->reports();
        $sector = Sector::create(['name' => 'Sector del proyecto', 'slug' => 'sector-proyecto', 'sort_order' => 2]);
        $assignment = SectorProyecto::create(['proyecto_id' => $included->proyecto_id, 'sector_id' => $sector->id]);
        $included->indicadorProyecto->update(['sector_proyecto_id' => $assignment->id]);

        $this->actingAs($admin)->get(route('beneficiaries.summary', ['sector_id' => $sector->id]))->assertOk()
            ->assertViewHas('reportCount', 1)
            ->assertViewHas('indicatorOptions', fn ($options) => $options->firstWhere('value', 'project:'.$included->indicador_proyecto_id)['sector_id'] === $sector->id)
            ->assertViewHas('groupedBeneficiaries', fn ($groups) => $groups->first()->project_sector_name === $sector->name);
        $this->get(route('beneficiaries.summary', ['sector_id' => $legacy->sector_id]))->assertOk()
            ->assertViewHas('reportCount', 1)
            ->assertViewHas('groupedBeneficiaries', fn ($groups) => $groups->first()->indicador_proyecto_id === null);
    }

    public function test_selected_indicator_is_applied_to_excel_and_mark_reported(): void
    {
        [$admin, $included, $excluded, $legacy] = $this->reports();
        $filters = ['indicator_filter' => 'project:'.$included->indicador_proyecto_id];
        $response = $this->actingAs($admin)->get(route('beneficiaries.export', $filters))->assertOk();
        $path = tempnam(sys_get_temp_dir(), 'indicator-filter-');
        try {
            file_put_contents($path, $response->streamedContent());
            $workbook = IOFactory::load($path);
            $rows = array_values(array_filter(array_slice($workbook->getActiveSheet()->toArray(), 1), fn ($row) => $row[0] !== null));
            $this->assertCount(1, $rows);
            $this->assertSame($included->beneficiaries()->first()->id, (int) $rows[0][0]);
            $this->assertSame('Indicador INCLUIDO', $rows[0][11]);
            $workbook->disconnectWorksheets();
        } finally {
            unlink($path);
        }
        $this->post(route('beneficiaries.mark-reported'), $filters + ['reported_at' => today()->toDateString()])->assertRedirect();
        $this->assertNotNull($included->beneficiaries()->first()->reported_at);
        $this->assertNull($legacy->beneficiaries()->first()->reported_at);
        $this->assertNull($excluded->beneficiaries()->first()->reported_at);
    }

    public function test_multiple_project_indicators_are_combined_and_preserved_in_the_form(): void
    {
        [$admin, $included, $excluded, $legacy] = $this->reports();
        $excluded->indicadorProyecto->indicador->update(['excluir_reporte_beneficiarios' => false]);
        $selected = ['project:'.$included->indicador_proyecto_id, 'project:'.$excluded->indicador_proyecto_id];
        $response = $this->actingAs($admin)->get(route('beneficiaries.summary', ['indicator_filter' => $selected]))->assertOk()
            ->assertViewHas('reportCount', 2)
            ->assertViewHas('summary', fn ($summary) => $summary['total'] === 2)
            ->assertViewHas('groupedBeneficiaries', fn ($groups) => $groups->sum('beneficiary_count') === 2 && $groups->every(fn ($group) => $group->indicador_proyecto_id !== null))
            ->assertSee('id="summary_indicator_id" multiple', false)
            ->assertSee('Seleccionar todos los indicadores');
        foreach ($selected as $value) {
            $response->assertSee('value="'.$value.'" selected', false)
                ->assertSee('name="indicator_filter[]" value="'.$value.'"', false);
        }

        // Detail links must narrow the selection back to that group's one indicator.
        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $links = (new \DOMXPath($document))->query('//a[@class="beneficiary-group-link"]');
        $this->assertCount(2, $links);
        foreach ($links as $link) {
            $this->assertStringNotContainsString('indicator_filter', $link->getAttribute('href'));
            $detail = $this->get($link->getAttribute('href'))->assertOk();
            $this->assertSame(1, $detail->viewData('summary')['total'], $link->getAttribute('href'));
        }
    }

    public function test_mixed_multiple_selection_keeps_exclusions_permissions_and_other_filters(): void
    {
        [$admin, $included, $excluded, $legacy] = $this->reports();
        $selected = ['project:'.$included->indicador_proyecto_id, 'legacy:'.$legacy->activity_id, 'project:'.$excluded->indicador_proyecto_id];
        $this->actingAs($admin)->get(route('beneficiaries.summary', ['indicator_filter' => [...$selected, $selected[0]]]))->assertOk()
            ->assertViewHas('filters', fn ($filters) => $filters['indicator_filter'] === $selected)
            ->assertViewHas('summary', fn ($summary) => $summary['total'] === 2);
        $this->get(route('beneficiaries.summary', ['indicator_filter' => $selected, 'from' => today()->addDay()->toDateString()]))->assertOk()
            ->assertViewHas('summary', fn ($summary) => $summary['total'] === 0);
        $legacy->beneficiaries()->update(['reported_at' => today()->toDateString()]);
        $this->get(route('beneficiaries.summary', ['indicator_filter' => $selected, 'reported' => '1']))->assertOk()
            ->assertViewHas('summary', fn ($summary) => $summary['total'] === 1);

        $reporter = User::factory()->create(['role' => 'reporter']);
        $included->update(['user_id' => $reporter->id]);
        $this->actingAs($reporter)->get(route('beneficiaries.summary', ['indicator_filter' => $selected, 'reported' => '']))->assertOk()
            ->assertViewHas('summary', fn ($summary) => $summary['total'] === 1);
    }

    public function test_empty_multiple_selection_means_all_and_invalid_members_are_rejected(): void
    {
        [$admin, $included] = $this->reports();
        $this->actingAs($admin);
        foreach ([[], [''], ['', null]] as $selection) {
            $this->call('GET', route('beneficiaries.summary'), ['indicator_filter' => $selection, 'indicador_proyecto_id' => $included->indicador_proyecto_id])
                ->assertOk()->assertViewHas('summary', fn ($summary) => $summary['total'] === 2);
        }
        foreach (['invalid', 'legacy:999999', ['nested']] as $invalid) {
            $this->getJson(route('beneficiaries.summary', ['indicator_filter' => ['project:'.$included->indicador_proyecto_id, $invalid]]))
                ->assertUnprocessable()->assertJsonValidationErrors('indicator_filter.1');
        }
    }

    public function test_multiple_selection_is_kept_in_excel_and_mark_reported(): void
    {
        [$admin, $included, $excluded, $legacy] = $this->reports();
        $selected = ['project:'.$included->indicador_proyecto_id, 'legacy:'.$legacy->activity_id, 'project:'.$excluded->indicador_proyecto_id];
        $response = $this->actingAs($admin)->get(route('beneficiaries.export', ['indicator_filter' => $selected]))->assertOk();
        $path = tempnam(sys_get_temp_dir(), 'indicator-multiple-');
        try {
            file_put_contents($path, $response->streamedContent());
            $workbook = IOFactory::load($path);
            $rows = array_values(array_filter(array_slice($workbook->getActiveSheet()->toArray(), 1), fn ($row) => $row[0] !== null));
            $this->assertCount(2, $rows);
            $this->assertEqualsCanonicalizing(
                [$included->beneficiaries()->first()->id, $legacy->beneficiaries()->first()->id],
                array_column($rows, 0),
            );
            $workbook->disconnectWorksheets();
        } finally {
            unlink($path);
        }
        $this->post(route('beneficiaries.mark-reported'), ['indicator_filter' => $selected, 'reported_at' => today()->toDateString()])
            ->assertRedirect(route('beneficiaries.summary', ['indicator_filter' => $selected, 'reported' => '0']));
        $this->assertNotNull($included->beneficiaries()->first()->reported_at);
        $this->assertNotNull($legacy->beneficiaries()->first()->reported_at);
        $this->assertNull($excluded->beneficiaries()->first()->reported_at);
    }

    private function indicatorData(string $code): array
    {
        return ['codigo' => $code, 'descripcion' => 'Indicador '.$code, 'unidad_conteo' => 'Personas',
            'espacio_coordinacion' => 'NNA', 'edad_desde' => 0, 'edad_hasta' => 120];
    }

    private function reports(): array
    {
        $admin = User::factory()->create(['role' => 'admin', 'can_mark_reported' => true]);
        $state = State::create(['code' => 'VE01', 'name' => 'Estado']);
        $municipality = Municipality::create(['state_id' => $state->id, 'code' => 'VE0101', 'name' => 'Municipio']);
        $parish = Parish::create(['municipality_id' => $municipality->id, 'code' => 'VE010101', 'name' => 'Parroquia']);
        $sector = Sector::create(['name' => 'Sector', 'slug' => 'sector', 'sort_order' => 1]);
        $activity = Activity::create(['sector_id' => $sector->id, 'code' => 'LEGACY', 'title' => 'Actividad anterior', 'sort_order' => 1, 'active' => true]);
        $donor = Donante::create(['nombre' => 'UNICEF', 'estatus' => true]);
        $project = Proyecto::create(['donante_id' => $donor->id, 'estatus' => true, 'codigo' => 'P-TEST', 'descripcion' => 'Proyecto']);
        $includedIndicator = Indicador::create($this->indicatorData('INCLUIDO'));
        $excludedIndicator = Indicador::create($this->indicatorData('EXCLUIDO') + ['excluir_reporte_beneficiarios' => true]);
        $reports = [];
        foreach ([$includedIndicator, $excludedIndicator, null] as $indicator) {
            $assignment = $indicator ? IndicadorProyecto::create([
                'proyecto_id' => $project->id, 'indicador_id' => $indicator->id, 'estatus' => true,
            ]) : null;
            $report = Report::create([
                'user_id' => $admin->id, 'proyecto_id' => $assignment ? $project->id : null,
                'indicador_proyecto_id' => $assignment?->id, 'report_date' => today(),
                'reporter_first_name' => 'Prueba', 'reporter_last_name' => 'Reporte', 'reporter_email' => $admin->email,
                'organization' => 'ASONACOP', 'state_id' => $state->id, 'municipality_id' => $municipality->id,
                'parish_id' => $parish->id, 'installation_type' => 'Comunidad / Espacio Comunitario',
                'place_name' => $indicator === $excludedIndicator ? 'Lugar excluido' : 'Lugar incluido',
                'sector_id' => $sector->id, 'activity_id' => $activity->id,
                'recurrence_status' => 'nuevo', 'total_beneficiaries' => 1, 'beneficiary_breakdown' => [],
            ]);
            $report->beneficiaries()->create(['full_name' => 'Persona de prueba', 'age' => 10, 'sex' => 'Mujer', 'is_recurrent' => false]);
            $reports[] = $report;
        }

        return [$admin, ...$reports];
    }
}
