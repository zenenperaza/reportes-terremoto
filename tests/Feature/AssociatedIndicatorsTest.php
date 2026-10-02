<?php

namespace Tests\Feature;

use App\Models\{Beneficiary, Donante, Indicador, IndicadorProyecto, Municipality, Parish, PlaceName, Proyecto, Report, ReportingPeriod, Sector, SectorProyecto, State, SystemSetting, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AssociatedIndicatorsTest extends TestCase
{
    use RefreshDatabase;

    private IndicadorProyecto $principal;
    private IndicadorProyecto $first;
    private IndicadorProyecto $second;
    private array $payload;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $state = State::create(['code' => 'VE01', 'name' => 'Estado']);
        $municipality = Municipality::create(['state_id' => $state->id, 'code' => 'VE0101', 'name' => 'Municipio']);
        $parish = Parish::create(['municipality_id' => $municipality->id, 'code' => 'VE010101', 'name' => 'Parroquia']);
        $sector = Sector::create(['name' => 'Protección', 'slug' => 'proteccion', 'sort_order' => 1]);
        $donor = Donante::create(['nombre' => 'Donante', 'estatus' => true]);
        $project = Proyecto::create(['donante_id' => $donor->id, 'codigo' => 'PROY-TEST', 'descripcion' => 'Proyecto', 'estatus' => true]);
        $project->estados()->attach($state);
        $sectorProject = SectorProyecto::create(['proyecto_id' => $project->id, 'sector_id' => $sector->id]);
        foreach (['principal', 'first', 'second'] as $key) {
            $indicator = Indicador::create(['codigo' => 'IND-'.$key, 'descripcion' => 'Indicador '.$key, 'unidad_conteo' => 'Personas', 'espacio_coordinacion' => 'NNA', 'edad_desde' => 0, 'edad_hasta' => 17]);
            $this->{$key} = IndicadorProyecto::create(['proyecto_id' => $project->id, 'sector_proyecto_id' => $sectorProject->id, 'indicador_id' => $indicator->id, 'estatus' => true]);
        }
        $this->principal->indicadoresAsociados()->sync([$this->first->id, $this->second->id]);
        PlaceName::create(['name' => 'Lugar de prueba']);
        SystemSetting::updateOrCreate(['key' => SystemSetting::CURRENT_PERIOD], ['value' => '2026-09']);
        $this->payload = ['report_date' => today()->toDateString(), 'organization' => 'ASONACOP',
            'state_id' => $state->id, 'municipality_id' => $municipality->id, 'parish_id' => $parish->id,
            'installation_type' => 'Comunidad / Espacio Comunitario', 'place_name' => 'Lugar de prueba',
            'proyecto_id' => $project->id, 'sector_proyecto_id' => $sectorProject->id, 'indicador_proyecto_id' => $this->principal->id];
    }

    private function person(string $name = 'Persona de prueba'): array
    {
        return ['has_informed_consent' => true, 'full_name' => $name, 'age' => 10, 'sex' => 'Mujer',
            'disability' => 'Ninguna', 'ethnicity' => 'Ninguna', 'pregnant_lactating' => 'N/A', 'is_recurrent' => false];
    }

    public function test_administrator_manages_direct_associations_and_reporter_cannot(): void
    {
        $this->get(route('sector-proyecto.indicadores.index', $this->principal->sector_proyecto_id))->assertOk()
            ->assertSeeInOrder(['<th>Estado</th>', '<th>Indicadores asociados</th>', '<th>Actividades</th>'], false)
            ->assertSeeInOrder(['status-active', route('indicador-proyecto.asociados', $this->principal), route('indicador-proyecto.actividades.index', $this->principal)], false);
        $this->get(route('indicador-proyecto.asociados', $this->principal))->assertOk()->assertSee('IND-first');
        $route = route('indicador-proyecto.asociados.update', $this->principal);
        $this->put($route, ['asociados' => [$this->first->id]])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame([$this->first->id], $this->principal->indicadoresAsociados()->get()->modelKeys());
        $this->putJson($route, ['asociados' => [$this->principal->id]])->assertUnprocessable();
        $this->putJson($route, ['asociados' => [$this->first->id, $this->first->id]])->assertUnprocessable();
        $this->put($route, [])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(0, $this->principal->indicadoresAsociados()->count());
        $this->actingAs(User::factory()->create(['role' => 'reporter']));
        $this->get(route('indicador-proyecto.asociados', $this->principal))->assertForbidden();
        $this->putJson($route, ['asociados' => [$this->first->id]])->assertForbidden();
    }

    public function test_new_form_exposes_direct_active_associations(): void
    {
        $this->second->update(['estatus' => false]);
        $this->get(route('reports.create'))->assertOk()->assertSee('Indicadores asociados')
            ->assertSee('associated-indicators.js')
            ->assertViewHas('projectIndicatorOptions', function ($options) {
                $catalog = collect($options[$this->principal->proyecto_id])->keyBy('id');
                return $catalog[$this->principal->id]['associatedIds'] === [$this->first->id]
                    && !$catalog[$this->principal->id]['isAssociated'] && $catalog[$this->first->id]['isAssociated'];
            });
        $this->principal->update(['estatus' => false]);
        $this->get(route('reports.create'))->assertOk()->assertViewHas('projectIndicatorOptions', function ($options) {
            return collect($options[$this->principal->proyecto_id])->firstWhere('id', $this->first->id)['isAssociated'];
        });
    }

    public function test_individual_save_creates_three_reports_and_reuses_them_for_next_person(): void
    {
        // Cycles are allowed in the catalog, but copies must never traverse them.
        $this->first->indicadoresAsociados()->attach($this->principal);
        $payload = $this->payload + ['beneficiary' => $this->person(), 'associated_indicator_ids' => [$this->first->id, $this->second->id]];
        $response = $this->postJson(route('beneficiaries.store'), $payload)->assertCreated()->assertJsonCount(2, 'associated_reports');
        $this->assertDatabaseCount('reports', 3);
        $this->assertDatabaseCount('beneficiaries', 3);
        $this->assertDatabaseCount('report_indicator_copies', 2);
        $reports = Report::orderBy('id')->get();
        $this->assertSame([$this->principal->id, $this->first->id, $this->second->id], $reports->pluck('indicador_proyecto_id')->all());
        foreach ($reports as $report) {
            $this->assertSame('2026-09', $report->reporting_period);
            $this->assertSame($this->payload['report_date'], $report->report_date->format('Y-m-d'));
            $this->assertSame(1, $report->total_beneficiaries);
            $this->assertSame('PERSONA DE PRUEBA', $report->beneficiaries->first()->full_name);
        }
        $payload['report_id'] = $response->json('report.id');
        $payload['beneficiary'] = $this->person('Segunda persona');
        $this->postJson(route('beneficiaries.store'), $payload)->assertOk()->assertJsonCount(2, 'associated_reports');
        $this->assertDatabaseCount('reports', 3);
        $this->assertDatabaseCount('beneficiaries', 6);
        $this->assertSame([2], Report::pluck('total_beneficiaries')->unique()->values()->all());
        $payload['associated_indicator_ids'] = [$this->first->id];
        $this->postJson(route('beneficiaries.store'), $payload)->assertConflict();
        $this->assertDatabaseCount('beneficiaries', 6);
    }

    public function test_batch_save_copies_all_people_and_no_selection_keeps_single_report(): void
    {
        $this->post(route('reports.store'), $this->payload + ['beneficiaries' => [$this->person(), $this->person('Otra persona')],
            'associated_indicator_ids' => [$this->first->id]])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseCount('reports', 2);
        $this->assertDatabaseCount('beneficiaries', 4);
        $this->assertSame([2], Report::pluck('total_beneficiaries')->unique()->values()->all());
        $this->postJson(route('beneficiaries.store'), $this->payload + ['beneficiary' => $this->person()])->assertCreated()->assertJsonCount(0, 'associated_reports');
        $this->assertDatabaseCount('reports', 3);
        $this->assertDatabaseCount('beneficiaries', 5);
    }

    public function test_browser_form_string_ids_create_associated_copies_in_both_save_paths(): void
    {
        // FormData sends IDs as strings; JSON fixtures with integer IDs hide this regression.
        $payload = $this->payload + ['beneficiary' => $this->person(), 'associated_indicator_ids' => [$this->first->id, $this->second->id]];
        array_walk_recursive($payload, function (&$value): void {
            $value = is_bool($value) ? ($value ? '1' : '0') : (string) $value;
        });
        $response = $this->post(route('beneficiaries.store'), $payload, ['Accept' => 'application/json'])
            ->assertCreated()->assertJsonCount(2, 'associated_reports');
        $this->assertDatabaseCount('reports', 3);
        $this->assertDatabaseCount('beneficiaries', 3);
        $payload['report_id'] = (string) $response->json('report.id');
        $this->post(route('beneficiaries.store'), $payload, ['Accept' => 'application/json'])->assertOk();
        $this->assertDatabaseCount('reports', 3);
        $this->assertDatabaseCount('beneficiaries', 6);

        $payload['beneficiaries'] = [$payload['beneficiary']];
        unset($payload['report_id'], $payload['beneficiary']);
        $this->post(route('reports.store'), $payload, ['Accept' => 'application/json'])->assertRedirect();
        $this->assertDatabaseCount('reports', 6);
        $this->assertDatabaseCount('beneficiaries', 9);
    }

    public function test_invalid_unconfigured_inactive_duplicate_and_age_mismatch_are_atomic(): void
    {
        $this->second->update(['estatus' => false]);
        foreach ([[$this->principal->id], [$this->second->id], [$this->first->id, $this->first->id], [999999], [['id' => $this->first->id]]] as $ids) {
            $this->postJson(route('beneficiaries.store'), $this->payload + ['beneficiary' => $this->person(), 'associated_indicator_ids' => $ids])->assertUnprocessable();
        }
        $this->first->indicador->update(['edad_desde' => 18, 'edad_hasta' => 120]);
        $this->postJson(route('beneficiaries.store'), $this->payload + ['beneficiary' => $this->person(), 'associated_indicator_ids' => [$this->first->id]])
            ->assertUnprocessable()->assertJsonValidationErrors('associated_indicator_ids');
        $this->postJson(route('reports.store'), $this->payload + ['beneficiaries' => [$this->person()], 'associated_indicator_ids' => [$this->first->id]])->assertUnprocessable();
        $this->assertDatabaseCount('reports', 0);
        $this->assertDatabaseCount('beneficiaries', 0);
    }

    public function test_associations_cannot_cross_projects_or_use_unconfigured_indicators(): void
    {
        $other = $this->principal->proyecto->replicate()->fill(['codigo' => 'OTHER']);
        $other->save();
        $otherSector = SectorProyecto::create(['proyecto_id' => $other->id, 'sector_id' => $this->principal->asignacionSector->sector_id]);
        $foreign = $this->first->replicate()->fill(['proyecto_id' => $other->id, 'sector_proyecto_id' => $otherSector->id]);
        $foreign->save();
        $this->putJson(route('indicador-proyecto.asociados.update', $this->principal), ['asociados' => [$foreign->id]])->assertUnprocessable();
        $this->postJson(route('beneficiaries.store'), $this->payload + ['beneficiary' => $this->person(), 'associated_indicator_ids' => [$foreign->id]])->assertUnprocessable();
        $this->principal->indicadoresAsociados()->detach($this->first);
        $this->postJson(route('beneficiaries.store'), $this->payload + ['beneficiary' => $this->person(), 'associated_indicator_ids' => [$this->first->id]])->assertUnprocessable();
        $this->assertDatabaseCount('reports', 0);
    }

    public function test_activities_and_services_are_not_attached_to_other_indicators_and_edits_do_not_duplicate(): void
    {
        $activity = \App\Models\Actividad::create(['codigo' => 'ACT-TEST', 'descripcion' => 'Actividad principal']);
        $assignment = $this->principal->asignacionesActividades()->create(['actividad_id' => $activity->id, 'estatus' => true]);
        $service = \App\Models\Servicio::create(['nombre' => 'Servicio principal']);
        $serviceAssignment = $assignment->asignacionesServicios()->create(['servicio_id' => $service->id, 'estatus' => true, 'cantidad_disponible' => 100]);
        $payload = $this->payload + ['beneficiary' => $this->person(), 'associated_indicator_ids' => [$this->first->id],
            'actividad_indicador_id' => $assignment->id, 'servicio_actividad_ids' => [$serviceAssignment->id]];
        $response = $this->postJson(route('beneficiaries.store'), $payload)->assertCreated();
        $source = Report::findOrFail($response->json('report.id'));
        $copy = Report::findOrFail($response->json('associated_reports.0.id'));
        $this->assertSame($assignment->id, $source->actividad_indicador_id);
        $this->assertSame(1, $source->serviciosActividad()->count());
        $this->assertNull($copy->actividad_indicador_id);
        $this->assertNull($copy->activity_id);
        $this->assertSame(0, $copy->serviciosActividad()->count());
        $this->get(route('reports.edit', $source))->assertOk()->assertViewHas('storedAssociatedIds', [$this->first->id]);
        $this->putJson(route('reports.update', $source), $this->payload + ['associated_indicator_ids' => [$this->first->id]])->assertUnprocessable();
        $this->assertDatabaseCount('reports', 2);
        $person = $source->beneficiaries()->first();
        $this->putJson(route('beneficiaries.update', $person), $this->person('Nombre corregido'))->assertOk();
        $this->assertSame('PERSONA DE PRUEBA', $copy->beneficiaries()->first()->full_name);
        $this->assertSame('NOMBRE CORREGIDO', $person->fresh()->full_name);
        $copyPayload = array_replace($this->payload, ['report_id' => $copy->id, 'indicador_proyecto_id' => $this->first->id, 'beneficiary' => $this->person('Persona independiente')]);
        $this->postJson(route('beneficiaries.store'), $copyPayload)->assertOk()->assertJsonCount(0, 'associated_reports');
        $this->assertSame(2, $copy->fresh()->total_beneficiaries);
        $this->assertSame(1, $source->fresh()->total_beneficiaries);
    }

    public function test_reviewed_or_deleted_copy_prevents_partial_append_and_closed_period_prevents_creation(): void
    {
        $payload = $this->payload + ['beneficiary' => $this->person(), 'associated_indicator_ids' => [$this->first->id, $this->second->id]];
        $response = $this->postJson(route('beneficiaries.store'), $payload)->assertCreated();
        $payload['report_id'] = $response->json('report.id');
        $lastCopy = Report::findOrFail($response->json('associated_reports.1.id'));
        $lastCopy->update(['status' => 'reviewed']);
        $this->postJson(route('beneficiaries.store'), $payload)->assertConflict();
        $this->assertDatabaseCount('beneficiaries', 3);
        $this->assertSame([1], Report::pluck('total_beneficiaries')->unique()->values()->all());
        $lastCopy->delete();
        $this->postJson(route('beneficiaries.store'), $payload)->assertConflict();
        $this->assertDatabaseCount('beneficiaries', 2);
        ReportingPeriod::updateOrCreate(['period' => '2026-09'], ['is_closed' => true, 'closed_at' => now()]);
        unset($payload['report_id']);
        $this->postJson(route('beneficiaries.store'), $payload)->assertConflict();
        $this->assertDatabaseCount('reports', 2);
    }
}
