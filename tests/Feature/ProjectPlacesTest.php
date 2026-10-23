<?php

namespace Tests\Feature;

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
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectPlacesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private State $state;
    private Municipality $municipality;
    private Parish $parish;
    private array $places = [];
    private array $projects = [];
    private array $indicators = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($this->admin);
        $this->state = State::create(['code' => 'A', 'name' => 'Estado A']);
        $this->municipality = Municipality::create(['state_id' => $this->state->id, 'code' => 'A1', 'name' => 'Municipio A1']);
        $this->parish = Parish::create(['municipality_id' => $this->municipality->id, 'code' => 'A11', 'name' => 'Parroquia A11']);
        foreach (['A', 'B', 'shared'] as $name) {
            $this->places[$name] = PlaceName::create(['name' => 'Lugar '.$name, 'state_id' => $this->state->id,
                'municipality_id' => $this->municipality->id, 'parish_id' => $this->parish->id,
                'installation_type' => 'Comunidad / Espacio Comunitario']);
        }
        $otherMunicipality = Municipality::create(['state_id' => $this->state->id, 'code' => 'A2', 'name' => 'Municipio A2']);
        $otherParish = Parish::create(['municipality_id' => $otherMunicipality->id, 'code' => 'A21', 'name' => 'Parroquia A21']);
        $this->places['otherMunicipality'] = PlaceName::create(['name' => 'Otro municipio', 'state_id' => $this->state->id,
            'municipality_id' => $otherMunicipality->id, 'parish_id' => $otherParish->id, 'installation_type' => 'Comunidad / Espacio Comunitario']);
        $otherState = State::create(['code' => 'B', 'name' => 'Estado B']);
        $outsideMunicipality = Municipality::create(['state_id' => $otherState->id, 'code' => 'B1', 'name' => 'Municipio B1']);
        $outsideParish = Parish::create(['municipality_id' => $outsideMunicipality->id, 'code' => 'B11', 'name' => 'Parroquia B11']);
        $this->places['outside'] = PlaceName::create(['name' => 'Fuera del estado', 'state_id' => $otherState->id,
            'municipality_id' => $outsideMunicipality->id, 'parish_id' => $outsideParish->id, 'installation_type' => 'Comunidad / Espacio Comunitario']);
        $this->places['incomplete'] = PlaceName::create(['name' => 'Sin ubicación']);
        $donor = Donante::create(['nombre' => 'Donante', 'estatus' => true]);
        $sector = Sector::create(['name' => 'Sector', 'slug' => 'sector']);
        foreach (['A', 'B', 'empty'] as $key) {
            $project = Proyecto::create(['donante_id' => $donor->id, 'codigo' => 'PROY-'.$key, 'descripcion' => 'Proyecto '.$key, 'estatus' => true]);
            $project->estados()->attach($this->state);
            $projectSector = SectorProyecto::create(['proyecto_id' => $project->id, 'sector_id' => $sector->id]);
            $indicator = Indicador::create(['codigo' => 'IND-'.$key, 'descripcion' => 'Indicador '.$key, 'unidad_conteo' => 'Personas',
                'espacio_coordinacion' => 'NNA', 'edad_desde' => 0, 'edad_hasta' => 120]);
            $this->indicators[$key] = IndicadorProyecto::create(['proyecto_id' => $project->id, 'sector_proyecto_id' => $projectSector->id,
                'indicador_id' => $indicator->id, 'estatus' => true]);
            $this->projects[$key] = $project;
            if ($key !== 'empty') {
                $project->lugares()->attach([$this->places[$key]->id, $this->places['shared']->id]);
            }
        }
    }

    private function configuration(string $code = 'NEW'): array
    {
        return ['donante_id' => $this->projects['A']->donante_id, 'codigo' => $code, 'descripcion' => 'Proyecto de prueba',
            'estatus' => '1', 'state_ids' => [(string) $this->state->id]];
    }

    private function header(string $key = 'A', string $place = 'A'): array
    {
        return ['report_date' => today()->toDateString(), 'organization' => 'ASONACOP', 'state_id' => $this->state->id,
            'municipality_id' => $this->municipality->id, 'parish_id' => $this->parish->id,
            'installation_type' => 'Comunidad / Espacio Comunitario', 'place_name' => $this->places[$place]->name,
            'proyecto_id' => $this->projects[$key]->id, 'sector_proyecto_id' => $this->indicators[$key]->sector_proyecto_id,
            'indicador_proyecto_id' => $this->indicators[$key]->id];
    }

    private function person(): array
    {
        return ['has_informed_consent' => true, 'full_name' => 'Persona de prueba', 'age' => 12, 'sex' => 'Mujer',
            'disability' => 'Ninguna', 'ethnicity' => 'Ninguna', 'pregnant_lactating' => 'N/A', 'is_recurrent' => false];
    }

    public function test_administrator_assigns_shared_places_on_create_edit_and_clears_without_deleting_catalog_entries(): void
    {
        $this->get(route('proyectos.create'))->assertOk()->assertSee('Lugares del proyecto')->assertSee('project-place-ids')
            ->assertSee('Seleccionar todos los lugares disponibles');
        $this->post(route('proyectos.store'), $this->configuration() + ['place_name_ids' => [(string) $this->places['A']->id,
            (string) $this->places['shared']->id]])->assertSessionHasNoErrors()->assertRedirect(route('proyectos.index'));
        $project = Proyecto::where('codigo', 'NEW')->firstOrFail();
        $this->assertSame([$this->places['A']->id, $this->places['shared']->id], $project->lugares()->orderBy('place_names.id')->get()->modelKeys());
        $this->assertSame(3, $this->places['shared']->proyectos()->count());
        $page = $this->get(route('proyectos.edit', $project))->assertOk();
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$page->getContent());
        $xpath = new \DOMXPath($dom);
        $this->assertSame(2, $xpath->query('//select[@name="place_name_ids[]" and @multiple]/option[@selected]')->length);
        // The action is injected by JavaScript, never rendered as a persisted place ID.
        $this->assertSame(0, $xpath->query('//select[@name="place_name_ids[]"]/option[@value="__all_project_places__"]')->length);
        $this->get(route('proyectos.show', $project))->assertOk()->assertSee('Lugares asignados')->assertSee('Lugar A');
        $this->put(route('proyectos.update', $project), $this->configuration() + ['place_name_ids' => [$this->places['B']->id]])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame([$this->places['B']->id], $project->lugares()->get()->modelKeys());
        $this->put(route('proyectos.update', $project), $this->configuration())->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(0, $project->lugares()->count());
        $this->assertSame(2, $this->places['shared']->proyectos()->count());
        $this->assertDatabaseCount('place_names', 6);
        $this->assertDatabaseCount('reports', 0);
        $this->get(route('place-names.index'))->assertOk()->assertSee('Lugar A')->assertSee('Lugar B');
    }

    public function test_project_places_must_exist_and_belong_to_its_states_and_optional_municipalities(): void
    {
        foreach ([[$this->places['outside']->id], [$this->places['incomplete']->id], [999999],
            [$this->places['A']->id, $this->places['A']->id]] as $ids) {
            $this->postJson(route('proyectos.store'), $this->configuration() + ['place_name_ids' => $ids])->assertUnprocessable();
        }
        $this->postJson(route('proyectos.store'), $this->configuration() + ['municipality_ids' => [$this->municipality->id],
            'place_name_ids' => [$this->places['otherMunicipality']->id]])->assertUnprocessable()->assertJsonValidationErrors('place_name_ids');
        $this->assertDatabaseCount('proyectos', 3);
        $this->post(route('proyectos.store'), $this->configuration() + ['place_name_ids' => [$this->places['otherMunicipality']->id]])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('lugar_proyecto', ['proyecto_id' => Proyecto::where('codigo', 'NEW')->value('id'),
            'place_name_id' => $this->places['otherMunicipality']->id]);
        $this->actingAs(User::factory()->create(['role' => 'reporter']))->putJson(route('proyectos.update', $this->projects['A']),
            $this->configuration('PROY-A'))->assertForbidden();
    }

    public function test_select_all_action_cannot_be_submitted_as_a_place_id(): void
    {
        $this->putJson(route('proyectos.update', $this->projects['A']), $this->configuration('PROY-A') + [
            'place_name_ids' => ['__all_project_places__', $this->places['A']->id],
        ])->assertUnprocessable()->assertJsonValidationErrors('place_name_ids.0');
        $this->assertSame([$this->places['A']->id, $this->places['shared']->id], $this->projects['A']->lugares()->get()->modelKeys());
    }

    public function test_report_form_only_includes_assigned_places_and_provides_project_specific_membership(): void
    {
        $user = User::factory()->create(['role' => 'reporter']);
        $user->projects()->attach($this->projects['A']);
        $user->assignedStates()->attach($this->state);
        $page = $this->actingAs($user)->get(route('reports.create'))->assertOk()->assertSee('Lugar A')->assertSee('Lugar shared')
            ->assertDontSee('Lugar B')->assertDontSee('Otro municipio')->assertDontSee('Fuera del estado')->assertDontSee('Sin ubicación');
        $this->assertSame([$this->places['A']->id, $this->places['shared']->id], $page->viewData('placeNames')->modelKeys());
        $this->assertSame([$this->places['A']->id, $this->places['shared']->id], $page->viewData('projectLocationOptions')[$this->projects['A']->id]['places']);
        $user->projects()->attach($this->projects['B']);
        $page = $this->get(route('reports.create'))->assertOk();
        $this->assertCount(3, $page->viewData('placeNames'));
        $this->assertNotContains($this->places['B']->id, $page->viewData('projectLocationOptions')[$this->projects['A']->id]['places']);
        $user->projects()->detach();
        $this->get(route('reports.create'))->assertOk()->assertViewHas('placeNames', fn ($places) => $places->isEmpty());
        $user->projects()->attach($this->projects['empty']);
        $this->get(route('reports.create'))->assertOk()->assertViewHas('placeNames', fn ($places) => $places->isEmpty());
    }

    public function test_both_save_paths_reject_unassigned_places_even_for_admin_and_accept_shared_assignments(): void
    {
        foreach (['reports.store' => ['beneficiaries' => [$this->person()]], 'beneficiaries.store' => ['beneficiary' => $this->person()]] as $route => $people) {
            foreach ([$this->header('A', 'B'), $this->header('empty', 'A')] as $header) {
                $this->postJson(route($route), $header + $people)->assertUnprocessable()->assertJsonValidationErrors('place_name');
            }
            $this->assertDatabaseCount('reports', $route === 'reports.store' ? 0 : 1);
            $this->postJson(route($route), $this->header('A', 'shared') + $people)->assertStatus($route === 'reports.store' ? 302 : 201);
        }
        $this->assertDatabaseCount('reports', 2);
        $this->assertDatabaseCount('beneficiaries', 2);
    }

    public function test_form_and_server_still_enforce_user_geographic_scope_for_assigned_places(): void
    {
        $user = User::factory()->create(['role' => 'reporter']);
        $user->projects()->attach($this->projects['A']);
        $user->assignedMunicipalities()->attach($this->municipality);
        $this->projects['A']->lugares()->attach($this->places['otherMunicipality']);
        $this->actingAs($user)->get(route('reports.create'))->assertOk()->assertDontSee('Otro municipio');
        $outside = $this->places['otherMunicipality'];
        $header = array_replace($this->header(), ['place_name' => $outside->name, 'municipality_id' => $outside->municipality_id, 'parish_id' => $outside->parish_id]);
        $this->postJson(route('beneficiaries.store'), $header + ['beneficiary' => $this->person()])->assertUnprocessable()->assertJsonValidationErrors('place_name');
        $this->postJson(route('beneficiaries.store'), $this->header('B', 'B') + ['beneficiary' => $this->person()])
            ->assertUnprocessable()->assertJsonValidationErrors('proyecto_id');
        $this->assertDatabaseCount('reports', 0);
    }

    public function test_existing_location_snapshot_can_be_kept_but_cannot_bypass_a_different_project_or_new_place(): void
    {
        $response = $this->postJson(route('beneficiaries.store'), $this->header() + ['beneficiary' => $this->person()])->assertCreated();
        $report = Report::findOrFail($response->json('report.id'));
        $person = $report->beneficiaries()->firstOrFail();
        $this->projects['A']->lugares()->detach($this->places['A']);
        $this->get(route('reports.edit', $report))->assertOk()->assertSee('Lugar A (ubicación guardada)');
        $this->putJson(route('reports.update', $report), $this->header())->assertOk();
        $this->putJson(route('beneficiaries.update-attention', $person), $this->header() + ['beneficiary' => $this->person()])->assertOk();
        $this->putJson(route('reports.update', $report), $this->header('A', 'B'))->assertUnprocessable()->assertJsonValidationErrors('place_name');
        $this->putJson(route('beneficiaries.update-attention', $person), $this->header('A', 'B') + ['beneficiary' => $this->person()])
            ->assertUnprocessable()->assertJsonValidationErrors('place_name');
        $this->putJson(route('reports.update', $report), $this->header('B', 'A'))->assertUnprocessable()->assertJsonValidationErrors('place_name');
        $this->postJson(route('beneficiaries.store'), $this->header() + ['report_id' => $report->id, 'beneficiary' => $this->person()])
            ->assertUnprocessable()->assertJsonValidationErrors('place_name');
        $this->assertSame('Lugar A', $report->fresh()->place_name);
        $this->places['A']->delete();
        $this->putJson(route('reports.update', $report), $this->header())->assertOk();
        $this->putJson(route('beneficiaries.update-attention', $person), $this->header() + ['beneficiary' => $this->person()])->assertOk();
        $this->assertDatabaseCount('reports', 1);
        $this->assertDatabaseCount('beneficiaries', 1);
        $this->assertDatabaseCount('lugar_proyecto', 3);
    }

    public function test_generated_community_location_remains_available_without_catalog_assignment(): void
    {
        $header = array_replace($this->header('empty'), ['is_community_location' => '1', 'latitude' => '10.5', 'longitude' => '-66.9']);
        $response = $this->postJson(route('beneficiaries.store'), $header + ['beneficiary' => $this->person()])->assertCreated();
        $this->assertDatabaseHas('reports', ['id' => $response->json('report.id'), 'place_name' => 'Comunidad '.$this->parish->name]);
        $this->assertSame(0, $this->projects['empty']->lugares()->count());
        $this->assertDatabaseCount('place_names', 6);
    }

    public function test_cleared_place_selection_is_not_reselected_after_validation_errors(): void
    {
        $page = $this->withSession(['_old_input' => ['codigo' => '', 'state_ids' => [$this->state->id]]])
            ->get(route('proyectos.edit', $this->projects['A']))->assertOk();
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$page->getContent());
        $this->assertSame(0, (new \DOMXPath($dom))->query('//select[@name="place_name_ids[]"]/option[@selected]')->length);
    }

    public function test_invalid_project_update_does_not_partially_modify_geography_or_places(): void
    {
        $this->putJson(route('proyectos.update', $this->projects['A']), array_replace($this->configuration('PROY-A'), [
            'descripcion' => 'Cambio no válido', 'place_name_ids' => [$this->places['outside']->id],
        ]))->assertUnprocessable()->assertJsonValidationErrors('place_name_ids');
        $this->assertSame('Proyecto A', $this->projects['A']->fresh()->descripcion);
        $this->assertSame([$this->places['A']->id, $this->places['shared']->id], $this->projects['A']->lugares()->get()->modelKeys());
        $this->assertSame([$this->state->id], $this->projects['A']->estados()->get()->modelKeys());
    }

    public function test_projects_without_place_selection_have_no_implicit_assignments_and_project_deletion_keeps_catalog(): void
    {
        $this->post(route('proyectos.store'), $this->configuration())->assertSessionHasNoErrors()->assertRedirect();
        $project = Proyecto::where('codigo', 'NEW')->firstOrFail();
        $this->assertSame(0, $project->lugares()->count());
        $project->lugares()->attach($this->places['shared']);
        $this->delete(route('proyectos.destroy', $project))->assertRedirect();
        $this->assertDatabaseMissing('lugar_proyecto', ['proyecto_id' => $project->id]);
        $this->assertDatabaseHas('place_names', ['id' => $this->places['shared']->id]);
        $this->assertSame(2, $this->places['shared']->proyectos()->count());
    }
}
