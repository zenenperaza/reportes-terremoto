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
