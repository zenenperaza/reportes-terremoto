<?php

namespace Tests\Feature;

use App\Models\{Donante, Municipality, Parish, Proyecto, Report, State, User, UserGroup};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecordProjectFilterTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $owner;
    private array $projects = [];
    private array $reports = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->owner = User::factory()->create(['role' => 'reporter']);
        $outsider = User::factory()->create(['role' => 'reporter']);
        $this->actingAs($this->admin);
        $state = State::create(['code' => 'P', 'name' => 'Estado']);
        $municipality = Municipality::create(['code' => 'P1', 'name' => 'Municipio', 'state_id' => $state->id]);
        $parish = Parish::create(['code' => 'P11', 'name' => 'Parroquia', 'municipality_id' => $municipality->id]);
        $donor = Donante::create(['nombre' => 'Donante', 'estatus' => true]);
        foreach ([1, 2, 3, 4, 0] as $number) {
            if ($number) $this->projects[$number] = Proyecto::create(['donante_id' => $donor->id,
                'codigo' => 'PROY-'.$number, 'nombre_alias' => 'Proyecto '.$number, 'descripcion' => 'Descripción', 'estatus' => $number !== 3]);
            $owner = $number === 4 ? $outsider : $this->owner;
            $report = Report::create(['proyecto_id' => $this->projects[$number]->id ?? null, 'user_id' => $owner->id,
                'report_date' => '2026-10-'.(10 + $number), 'reporting_period' => $number === 2 ? '2026-09' : '2026-10',
                'state_id' => $state->id, 'municipality_id' => $municipality->id, 'parish_id' => $parish->id,
                'reporter_first_name' => 'Prueba', 'reporter_last_name' => '', 'reporter_email' => $owner->email,
                'organization' => 'ASONACOP', 'installation_type' => 'Comunidad / Espacio Comunitario', 'place_name' => 'Lugar '.$number,
                'recurrence_status' => 'no_recurrente', 'total_beneficiaries' => 1, 'beneficiary_breakdown' => []]);
            $person = $report->beneficiaries()->create(['full_name' => 'NAME-'.$number, 'age' => 30, 'sex' => 'Mujer', 'is_recurrent' => false]);
            if ($number === 1) $person->forceFill(['reported_at' => '2026-10-20', 'reported' => true])->save();
            $this->reports[$number] = $report;
        }
    }

    public function test_default_is_all_projects_and_project_multiselect_comes_first(): void
    {
        $response = $this->get(route('reports.index'))->assertOk()
            ->assertViewHas('filters', fn ($filters) => $filters['project_ids'] === [])
            ->assertViewHas('projectOptions', fn ($options) => $options->count() === 4)
            ->assertSee('record-project-filter.js')->assertSee('data-placeholder="Todos los proyectos"', false)
            ->assertSeeInOrder(['id="records-project-filter"', 'Estado de reporte', 'id="reporting-period"', 'Indicadores Resumen'], false);
        $html = $response->getContent();
        preg_match('/<select id="records-project-filter".*?<\/select>/s', $html, $select);
        $this->assertStringContainsString('multiple', $select[0]);
        $this->assertStringContainsString('Seleccionar todos los proyectos', $select[0]);
        $this->assertStringNotContainsString(' selected', $select[0]);
        $this->getJson(route('reports.index', ['draw' => 1]))->assertOk()->assertJsonPath('recordsFiltered', 5);
    }

    public function test_admin_table_and_exports_apply_one_or_multiple_projects_without_changing_total_visibility(): void
    {
        foreach ([[1], [1, 2], [3]] as $numbers) {
            $ids = array_map(fn ($number) => $this->projects[$number]->id, $numbers);
            $this->getJson(route('reports.index', ['draw' => 1, 'project_ids' => $ids]))->assertOk()
                ->assertJsonPath('recordsTotal', 5)->assertJsonPath('recordsFiltered', count($ids))->assertJsonCount(count($ids), 'data');
            $this->get(route('reports.index', ['project_ids' => $ids]))->assertOk()
                ->assertViewHas('filters', fn ($filters) => $filters['project_ids'] === $ids);
            foreach (['excel', 'pdf', 'copy', 'csv', 'print'] as $format) {
                $export = $this->getJson(route('reports.index', ['draw' => 1, 'project_ids' => $ids, 'export_type' => $format, 'length' => 1, 'start' => 99]))->assertOk();
                $rows = json_decode($export->streamedContent(), true)['data'];
                $this->assertCount(count($ids), $rows);
                $this->assertEqualsCanonicalizing(array_map(fn ($n) => 'NAME-'.$n, $numbers), array_column($rows, 'full_name'));
            }
        }
    }

    public function test_project_filter_intersects_reported_period_date_and_user_filters(): void
    {
        $filters = ['draw' => 1, 'project_ids' => [$this->projects[1]->id, $this->projects[2]->id], 'reported' => '1',
            'reporting_period' => ['2026-10'], 'from' => '2026-10-11', 'to' => '2026-10-11', 'user_id' => $this->owner->id];
        $this->getJson(route('reports.index', $filters))->assertOk()->assertJsonPath('recordsFiltered', 1)->assertJsonPath('data.0.full_name', 'NAME-1');
        $this->getJson(route('reports.index', array_replace($filters, ['project_ids' => [$this->projects[2]->id]])))
            ->assertOk()->assertJsonPath('recordsFiltered', 0);
    }

    public function test_reporter_filter_keeps_team_visibility_and_historical_inactive_projects(): void
    {
        $this->actingAs($this->owner)->get(route('reports.index'))->assertOk()
            ->assertViewHas('projectOptions', fn ($options) => $options->modelKeys() === array_map(fn ($n) => $this->projects[$n]->id, [1, 2, 3]));
        $this->get(route('reports.index', ['project_ids' => [$this->projects[1]->id, $this->projects[3]->id]]))->assertOk()
            ->assertViewHas('reports', fn ($reports) => $reports->count() === 2);
        $this->getJson(route('reports.index', ['project_ids' => [$this->projects[4]->id]]))->assertUnprocessable();
        $this->owner->projects()->attach($this->projects[4]);
        $this->get(route('reports.index', ['project_ids' => [$this->projects[4]->id]]))->assertOk()
            ->assertViewHas('reports', fn ($reports) => $reports->isEmpty());
    }

    public function test_coordinator_can_filter_team_projects_but_never_get_outsider_data(): void
    {
        $coordinator = User::factory()->create(['role' => 'coordinator']);
        $group = UserGroup::create(['name' => 'Equipo', 'is_active' => true]);
        $group->users()->attach([$coordinator->id, $this->owner->id]);
        $this->actingAs($coordinator)->getJson(route('reports.index', ['draw' => 1, 'project_ids' => [$this->projects[2]->id]]))
            ->assertOk()->assertJsonPath('recordsFiltered', 1)->assertDontSee('NAME-4');
        $coordinator->projects()->attach($this->projects[4]);
        $this->getJson(route('reports.index', ['draw' => 1, 'project_ids' => [$this->projects[4]->id]]))->assertOk()->assertJsonPath('recordsFiltered', 0);
    }

    public function test_admin_csv_export_uses_the_same_project_selection(): void
    {
        $response = $this->get(route('reports.export', ['project_ids' => [$this->projects[1]->id, $this->projects[3]->id]]))->assertOk();
        $csv = $response->streamedContent();
        $this->assertStringContainsString('NAME-1', $csv); $this->assertStringContainsString('NAME-3', $csv);
        foreach ([0, 2, 4] as $n) $this->assertStringNotContainsString('NAME-'.$n, $csv);
    }

    public function test_malformed_or_unavailable_projects_are_rejected_for_table_and_exports(): void
    {
        foreach (['1', ['__all__'], [999999], [-1], [1, 1], [['nested']], ['named' => 1]] as $ids) {
            $this->getJson(route('reports.index', ['draw' => 1, 'project_ids' => $ids]))->assertUnprocessable();
            $this->getJson(route('reports.export', ['project_ids' => $ids]))->assertUnprocessable();
        }
        $this->getJson(route('reports.index', ['draw' => 1, 'project_ids' => '']))->assertOk()->assertJsonPath('recordsFiltered', 5);
    }
}
