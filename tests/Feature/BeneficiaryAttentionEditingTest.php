<?php

namespace Tests\Feature;

use App\Models\Actividad;
use App\Models\ActividadIndicador;
use App\Models\Beneficiary;
use App\Models\Donante;
use App\Models\Indicador;
use App\Models\IndicadorProyecto;
use App\Models\Municipality;
use App\Models\Parish;
use App\Models\PlaceName;
use App\Models\Proyecto;
use App\Models\Report;
use App\Models\Sector;
use App\Models\SectorProyecto;
use App\Models\Servicio;
use App\Models\ServicioActividad;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BeneficiaryAttentionEditingTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin']);
        $owner = User::factory()->create(['role' => 'reporter']);
        $state = State::create(['code' => 'VE01', 'name' => 'Estado']);
        $municipality = Municipality::create(['state_id' => $state->id, 'code' => 'M01', 'name' => 'Municipio']);
        $parish = Parish::create(['municipality_id' => $municipality->id, 'code' => 'P01', 'name' => 'Parroquia']);
        $donor = Donante::create(['nombre' => 'Donante', 'estatus' => true]);
        $payloads = [];
        foreach ([1, 2] as $number) {
            $project = Proyecto::create(['donante_id' => $donor->id, 'codigo' => 'PROY-'.$number, 'descripcion' => 'Proyecto '.$number, 'estatus' => true]);
            $project->estados()->attach($state);
            $owner->projects()->attach($project);
            $sector = Sector::create(['name' => 'Sector '.$number, 'slug' => 'sector-'.$number]);
            $sectorAssignment = SectorProyecto::create(['proyecto_id' => $project->id, 'sector_id' => $sector->id]);
            $indicator = Indicador::create(['codigo' => 'IND-'.$number, 'descripcion' => 'Indicador '.$number, 'unidad_conteo' => 'Personas', 'espacio_coordinacion' => 'NNA', 'edad_desde' => 0, 'edad_hasta' => 120]);
            $assignment = IndicadorProyecto::create(['proyecto_id' => $project->id, 'sector_proyecto_id' => $sectorAssignment->id, 'indicador_id' => $indicator->id, 'estatus' => true]);
            $activity = Actividad::create(['codigo' => 'ACT-'.$number, 'descripcion' => 'Actividad '.$number, 'estatus' => true]);
            $projectActivity = ActividadIndicador::create(['indicador_proyecto_id' => $assignment->id, 'actividad_id' => $activity->id, 'estatus' => true]);
            $service = Servicio::create(['nombre' => 'Servicio '.$number, 'estatus' => true]);
            $serviceAssignment = ServicioActividad::create(['actividad_indicador_id' => $projectActivity->id, 'servicio_id' => $service->id, 'estatus' => true]);
            PlaceName::create(['name' => 'Lugar '.$number, 'state_id' => $state->id, 'municipality_id' => $municipality->id, 'parish_id' => $parish->id, 'installation_type' => 'Comunidad / Espacio Comunitario']);
            $payloads[] = [
                'report_date' => today()->toDateString(), 'reporter_first_name' => 'Original', 'reporter_last_name' => 'Persona', 'reporter_email' => $owner->email,
                'organization' => 'ASONACOP', 'state_id' => $state->id, 'municipality_id' => $municipality->id, 'parish_id' => $parish->id,
                'place_name' => 'Lugar '.$number, 'installation_type' => 'Comunidad / Espacio Comunitario',
                'proyecto_id' => $project->id, 'sector_proyecto_id' => $sectorAssignment->id, 'indicador_proyecto_id' => $assignment->id,
                'actividad_indicador_id' => $projectActivity->id, 'servicio_actividad_ids' => [$serviceAssignment->id],
            ];
        }
        $owner->assignedStates()->attach($state);
        $report = Report::create($payloads[0] + ['user_id' => $owner->id, 'total_beneficiaries' => 2, 'beneficiary_breakdown' => [], 'recurrence_status' => 'no_recurrente', 'status' => 'submitted']);
        $report->serviciosActividad()->sync($payloads[0]['servicio_actividad_ids']);
        $report->created_at = now()->subMonth();
        $report->save();
        foreach ([10, 20] as $age) {
            $report->beneficiaries()->create(['has_informed_consent' => true, 'full_name' => 'Persona '.$age, 'age' => $age, 'sex' => 'Mujer', 'disability' => 'Ninguna', 'ethnicity' => 'Ninguna', 'pregnant_lactating' => 'Ninguna', 'is_recurrent' => false]);
        }
        $first = $report->beneficiaries()->first();
        $second = $report->beneficiaries()->get()->last();
        $this->actingAs($admin);

        return [$report, $first, $second, $payloads[0], $payloads[1], $owner];
    }

    private function person(Beneficiary $beneficiary): array
    {
        return $beneficiary->only(['has_informed_consent', 'full_name', 'age', 'sex', 'national_id', 'phone', 'disability', 'ethnicity', 'pregnant_lactating', 'is_recurrent']);
    }

    public static function independentPermissions(): array
    {
        return [
            'none' => [null, false, false, false, false],
            'edit group' => ['editar registros', true, false, false, false],
            'delete group' => ['eliminar registros', false, true, false, false],
            'edit person' => ['editar beneficiarios', false, false, true, false],
            'delete person' => ['eliminar beneficiarios', false, false, false, true],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('independentPermissions')]
    public function test_each_action_requires_its_own_permission(?string $permission, bool $editReport, bool $deleteReport, bool $editPerson, bool $deletePerson): void
    {
        [$report, $first, $second, $original] = $this->fixture();
        $role = \Spatie\Permission\Models\Role::findByName('admin', 'web');
        $role->syncPermissions(array_filter(['ver detalle de registros', $permission]));
        $this->actingAs(auth()->user()->fresh());

        $response = $this->get(route('reports.show', $report))->assertOk()
            ->assertViewHas('canEditReport', $editReport)
            ->assertViewHas('canDeleteReport', $deleteReport)
            ->assertViewHas('canEditBeneficiaries', $editPerson)
            ->assertViewHas('canDeleteBeneficiaries', $deletePerson);
        if ($editPerson) {
            $response->assertSee('aria-label="Editar beneficiario"', false);
        } else {
            $response->assertDontSee('aria-label="Editar beneficiario"', false);
        }
        if ($deletePerson) {
            $response->assertSee('aria-label="Eliminar beneficiario"', false);
        } else {
            $response->assertDontSee('aria-label="Eliminar beneficiario"', false);
        }

        $this->get(route('reports.edit', $report))->assertStatus($editReport ? 200 : 403);
        $this->get(route('reports.edit', ['report' => $report, 'beneficiary' => $first->id]))
            ->assertStatus($editPerson ? 200 : 403);
        $this->putJson(route('reports.update', $report), $original)->assertStatus($editReport ? 200 : 403);
        $this->putJson(route('beneficiaries.update', $first), $this->person($first))->assertStatus($editPerson ? 200 : 403);
        // A deleted catalog entry must not prevent authorized individual edits.
        PlaceName::where('name', 'Lugar 1')->delete();
        $this->putJson(route('beneficiaries.update-attention', $first), $original + ['beneficiary' => $this->person($first)])
            ->assertStatus($editPerson ? 200 : 403);
        $this->deleteJson(route('beneficiaries.destroy', $first))->assertStatus($deletePerson ? 200 : 403);
        if ($deletePerson) {
            $this->assertDatabaseHas('beneficiaries', ['id' => $second->id]);
            $this->deleteJson(route('beneficiaries.destroy', $second))->assertForbidden();
            $this->assertDatabaseHas('reports', ['id' => $report->id]);
        }
        $this->delete(route('reports.destroy', $report))->assertStatus($deleteReport ? 302 : 403);
    }

    public function test_group_and_individual_forms_have_distinct_scopes_and_keep_saved_location(): void
    {
        [$report, $first, $second] = $this->fixture();
        $this->get(route('reports.edit', $report))->assertOk()->assertViewHas('editingBeneficiary', null)
            ->assertSee('Guardar cambios de todo el grupo')->assertDontSee('data-beneficiary-update-url=', false);
        $this->get(route('reports.edit', ['report' => $report, 'beneficiary' => $second->id]))->assertOk()
            ->assertViewHas('editingBeneficiary', fn ($person) => $person->id === $second->id)
            ->assertSee('Guardar cambios del beneficiario')->assertSee('data-beneficiary-update-url=', false)
            ->assertDontSee('data-report-update-url=', false)->assertSee('Lugar 1 (ubicación guardada)');
        $this->get(route('reports.edit', ['report' => $report, 'beneficiary' => 99999]))->assertNotFound();
    }

    public function test_beneficiary_actions_use_accessible_icons_and_keep_their_targets(): void
    {
        [$report, $first] = $this->fixture();

        $this->get(route('reports.show', $report))->assertOk()
            ->assertSee('class="beneficiary-row-actions"', false)
            ->assertSee('aria-label="Editar beneficiario"', false)
            ->assertSee('aria-label="Eliminar beneficiario"', false)
            ->assertSee('class="ri-pencil-line" aria-hidden="true"', false)
            ->assertSee('class="ri-delete-bin-5-line" aria-hidden="true"', false)
            ->assertSee('class="btn btn-primary btn-icon waves-effect waves-light"', false)
            ->assertSee('class="btn btn-danger btn-icon waves-effect waves-light beneficiary-delete-button"', false)
            ->assertSee('href="'.e(route('reports.edit', ['report' => $report, 'beneficiary' => $first->id])).'"', false)
            ->assertSee('data-beneficiary-id="'.$first->id.'"', false);
    }

    public function test_individual_context_change_moves_only_one_person_and_keeps_identity_dates_status_and_evidence(): void
    {
        [$report, $first, $second, $original, $destination, $owner] = $this->fixture();
        $first->forceFill(['reported' => true, 'reported_at' => today()])->save();
        $originalPerson = $first->fresh()->getAttributes();
        $untouchedPerson = $second->getAttributes();
        $path = 'reports/'.$report->id.'/evidence.pdf';
        Storage::disk('local')->put($path, 'evidence-content');
        $report->evidences()->create(['slot' => 1, 'original_name' => 'evidence.pdf', 'path' => $path, 'mime_type' => 'application/pdf', 'size' => 16]);

        $destination['reporter_first_name'] = 'Forged';
        $destination['reporter_email'] = 'forged@example.test';
        $response = $this->putJson(route('beneficiaries.update-attention', $first), $destination + ['user_id' => 99999, 'beneficiary' => $this->person($first)])
            ->assertOk()->assertJsonPath('separated', true)->assertJsonPath('beneficiary.id', $first->id);
        $target = Report::findOrFail($response->json('report.id'));
        $this->assertNotEquals($report->id, $target->id);
        $this->assertSame($target->id, $first->refresh()->report_id);
        $this->assertSame($destination['indicador_proyecto_id'], $target->indicador_proyecto_id);
        $this->assertSame('Lugar 2', $target->place_name);
        $this->assertSame($owner->id, $target->user_id);
        $this->assertSame('Original', $target->reporter_first_name);
        $this->assertSame($owner->email, $target->reporter_email);
        $this->assertEquals($report->created_at, $target->created_at);
        foreach (['created_at', 'reported', 'reported_at'] as $field) {
            $this->assertEquals($originalPerson[$field], $first->getAttributes()[$field]);
        }
        $this->assertSame($untouchedPerson, $second->fresh()->getAttributes());
        $this->assertSame($original['indicador_proyecto_id'], $report->refresh()->indicador_proyecto_id);
        $this->assertSame('Lugar 1', $report->place_name);
        $this->assertEquals(1, $target->total_beneficiaries);
        $this->assertEquals(1, $report->total_beneficiaries);
        $this->assertEquals($destination['servicio_actividad_ids'], $target->serviciosActividad()->pluck('servicio_actividad.id')->all());
        $this->assertEquals($original['servicio_actividad_ids'], $report->serviciosActividad()->pluck('servicio_actividad.id')->all());
        $copy = $target->evidences()->firstOrFail();
        $this->assertNotSame($path, $copy->path);
        $this->assertSame('evidence-content', Storage::disk('local')->get($copy->path));
        Storage::disk('local')->assertExists($path);
        $this->assertDatabaseCount('beneficiaries', 2);
        $this->assertDatabaseCount('reports', 2);
    }

    public function test_personal_changes_keep_group_and_do_not_touch_other_people(): void
    {
        [$report, $first, $second, $original] = $this->fixture();
        $person = $this->person($first);
        $person['full_name'] = 'Nombre corregido';
        $person['age'] = 12;
        $snapshot = $second->getAttributes();
        $this->putJson(route('beneficiaries.update-attention', $first), $original + ['beneficiary' => $person])
            ->assertOk()->assertJsonPath('separated', false)->assertJsonPath('report.id', $report->id);
        $this->assertSame('NOMBRE CORREGIDO', $first->refresh()->full_name);
        $this->assertSame($snapshot, $second->fresh()->getAttributes());
        $this->assertDatabaseCount('reports', 1);
    }

    public function test_changing_the_only_beneficiary_reuses_parent_instead_of_leaving_an_empty_report(): void
    {
        [$report, $first, $second, $original, $destination] = $this->fixture();
        $second->delete();
        $this->putJson(route('beneficiaries.update-attention', $first), $destination + ['beneficiary' => $this->person($first)])
            ->assertOk()->assertJsonPath('separated', false)->assertJsonPath('report.id', $report->id);
        $this->assertSame('Lugar 2', $report->refresh()->place_name);
        $this->assertDatabaseCount('reports', 1);
    }

    public function test_group_edit_changes_shared_data_not_individual_data_and_checks_every_age(): void
    {
        [$report, $first, $second, $original, $destination] = $this->fixture();
        IndicadorProyecto::find($destination['indicador_proyecto_id'])->indicador->update(['edad_desde' => 18]);
        $this->putJson(route('reports.update', $report), $destination)->assertUnprocessable()->assertJsonValidationErrors('indicador_proyecto_id');
        IndicadorProyecto::find($destination['indicador_proyecto_id'])->indicador->update(['edad_desde' => 0]);
        $this->putJson(route('reports.update', $report), $destination + ['beneficiary' => $this->person($first)])
            ->assertUnprocessable()->assertJsonValidationErrors('beneficiary');
        $snapshot = $report->beneficiaries()->get()->map->getAttributes()->all();
        $this->putJson(route('reports.update', $report), $destination)->assertOk();
        $this->assertSame('Lugar 2', $report->refresh()->place_name);
        $this->assertSame($snapshot, $report->beneficiaries()->get()->map->getAttributes()->all());
        $this->assertDatabaseCount('reports', 1);
    }

    public function test_new_indicator_validates_selected_person_age_and_rejects_incompatible_services(): void
    {
        [$report, $first, $second, $original, $destination] = $this->fixture();
        IndicadorProyecto::find($destination['indicador_proyecto_id'])->indicador->update(['edad_desde' => 18]);
        $this->putJson(route('beneficiaries.update-attention', $first), $destination + ['beneficiary' => $this->person($first)])
            ->assertUnprocessable()->assertJsonValidationErrors('beneficiary.age');
        $person = $this->person($first);
        $person['age'] = 21;
        $invalid = $destination;
        $invalid['servicio_actividad_ids'] = $original['servicio_actividad_ids'];
        $this->putJson(route('beneficiaries.update-attention', $first), $invalid + ['beneficiary' => $person])
            ->assertUnprocessable()->assertJsonValidationErrors('servicio_actividad_ids');
        $this->putJson(route('beneficiaries.update-attention', $first), $destination + ['beneficiary' => $person])->assertOk();
        $this->assertEquals(21, $first->refresh()->age);
    }

    public function test_stored_location_survives_catalog_removal_but_new_unknown_locations_are_rejected(): void
    {
        [$report, $first, $second, $original] = $this->fixture();
        PlaceName::where('name', 'Lugar 1')->delete();
        $this->get(route('reports.edit', ['report' => $report, 'beneficiary' => $first->id]))->assertOk()
            ->assertViewHas('communityLocation', false)->assertSee('data-original-location="1"', false)->assertSee('Lugar 1 (ubicación guardada)');
        $this->putJson(route('beneficiaries.update-attention', $first), $original + ['beneficiary' => $this->person($first)])
            ->assertOk()->assertJsonPath('separated', false);
        $this->putJson(route('reports.update', $report), $original)->assertOk();
        $changed = $original;
        $changed['place_name'] = 'Lugar inventado';
        $this->putJson(route('beneficiaries.update-attention', $first), $changed + ['beneficiary' => $this->person($first)])
            ->assertUnprocessable()->assertJsonValidationErrors('place_name');
        $this->assertSame('Lugar 1', $report->fresh()->place_name);
    }

    public function test_other_users_and_reviewed_reports_cannot_be_changed(): void
    {
        [$report, $first, $second, $original, $destination, $owner] = $this->fixture();
        $other = User::factory()->create(['role' => 'reporter']);
        $this->actingAs($other)->putJson(route('beneficiaries.update-attention', $first), $destination + ['beneficiary' => $this->person($first)])->assertForbidden();
        $report->update(['status' => 'reviewed']);
        $this->actingAs($owner)->putJson(route('beneficiaries.update-attention', $first), $original + ['beneficiary' => $this->person($first)])->assertConflict();
        $this->assertDatabaseCount('reports', 1);
    }

    public function test_failed_evidence_copy_rolls_back_person_and_group_changes(): void
    {
        [$report, $first, $second, $original, $destination] = $this->fixture();
        $report->evidences()->create(['slot' => 1, 'original_name' => 'missing.pdf', 'path' => 'reports/'.$report->id.'/missing.pdf', 'mime_type' => 'application/pdf', 'size' => 10]);
        $this->putJson(route('beneficiaries.update-attention', $first), $destination + ['beneficiary' => $this->person($first)])->assertStatus(500);
        $this->assertSame($report->id, $first->refresh()->report_id);
        $this->assertSame('Lugar 1', $report->fresh()->place_name);
        $this->assertDatabaseCount('reports', 1);
        $this->assertDatabaseCount('beneficiaries', 2);
        $this->assertDatabaseCount('evidences', 1);
    }

    public function test_stale_form_cannot_move_a_person_that_was_already_separated(): void
    {
        [$report, $first, $second, $original, $destination] = $this->fixture();
        $payload = $destination + ['source_report_id' => $report->id, 'beneficiary' => $this->person($first)];
        $response = $this->putJson(route('beneficiaries.update-attention', $first), $payload)->assertOk();
        $this->putJson(route('beneficiaries.update-attention', $first), $payload)->assertConflict();
        $this->assertSame($response->json('report.id'), $first->refresh()->report_id);
        $this->assertDatabaseCount('reports', 2);
    }
}
