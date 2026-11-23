<?php

namespace Tests\Feature;

use App\Models\{Municipality, Parish, Report, Sector, State, User, UserGroup};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportListsTest extends TestCase
{
    use RefreshDatabase;
    use \Tests\Concerns\CreatesReportProjectContext;

    private User $actor;
    private User $peer;
    private UserGroup $group;
    private Report $own;
    private Report $second;
    private Report $shared;
    private Report $foreign;
    private Report $otherProject;
    private array $headers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actor = User::factory()->create(['role' => 'reporter']);
        $this->actor->syncRoles([]);
        $this->actor->givePermissionTo(['solo ver registros', 'ver detalle de registros']);
        $this->peer = User::factory()->create(['role' => 'reporter']);
        $outsider = User::factory()->create(['role' => 'reporter']);
        $this->group = UserGroup::create(['name' => 'Equipo', 'is_active' => true, 'allow_member_editing' => false]);
        $this->group->users()->attach([$this->actor->id, $this->peer->id]);
        $state = State::create(['code' => 'L', 'name' => 'Estado']);
        $municipality = Municipality::create(['state_id' => $state->id, 'code' => 'L1', 'name' => 'Municipio']);
        $parish = Parish::create(['municipality_id' => $municipality->id, 'code' => 'L11', 'name' => 'Parroquia']);
        $sector = Sector::create(['name' => 'Sector', 'slug' => 'report-lists']);
        $context = $this->reportProjectContext($this->actor, $state, $sector);
        $otherContext = $this->reportProjectContext($this->actor, $state, $sector);
        $this->headers = $context + ['user_id' => $this->actor->id, 'reporting_period' => '2026-11', 'report_date' => '2026-11-20',
            'reporter_first_name' => 'Persona', 'reporter_last_name' => 'Registradora', 'reporter_email' => $this->actor->email,
            'organization' => 'ASONACOP', 'state_id' => $state->id, 'municipality_id' => $municipality->id,
            'parish_id' => $parish->id, 'sector_id' => $sector->id, 'installation_type' => 'Comunidad / Espacio Comunitario',
            'place_name' => 'Lugar propio', 'recurrence_status' => 'no_recurrente', 'total_beneficiaries' => 2, 'beneficiary_breakdown' => []];
        $this->own = $this->listing();
        $this->second = $this->listing();
        $this->shared = $this->listing(['user_id' => $this->peer->id, 'place_name' => 'Lugar compartido', 'reporting_period' => '2026-10', 'report_date' => '2026-10-21']);
        $this->foreign = $this->listing(['user_id' => $outsider->id, 'place_name' => 'Lugar de otro equipo']);
        $this->otherProject = $this->listing($otherContext + ['place_name' => 'Lugar de otro proyecto']);
        $this->actingAs($this->actor);
    }

    private function listing(array $changes = []): Report
    {
        $report = Report::create(array_replace($this->headers, $changes));
        foreach (['PERSONA PRIVADA A', 'PERSONA PRIVADA B'] as $name) {
            $report->beneficiaries()->create(['full_name' => $name, 'national_id' => 'V12345678', 'age' => 20, 'sex' => 'Hombre', 'is_recurrent' => false]);
        }
        return $report;
    }

    public function test_records_have_a_lists_button_preserving_filters_and_a_static_route_before_model_binding(): void
    {
        $filters = ['project_ids' => [$this->own->proyecto_id], 'reporting_period' => ['2026-11'], 'reported' => '0'];
        $page = $this->get(route('reports.index', $filters))->assertOk()->assertSee('Consultar listados');
        preg_match('/href="([^"]*\/reportes\/listados[^\"]*)"/', $page->getContent(), $match);
        $this->assertNotEmpty($match);
        parse_str(parse_url(html_entity_decode($match[1]), PHP_URL_QUERY) ?? '', $query);
        $this->assertSame([(string) $this->own->proyecto_id], $query['project_ids']);
        $this->assertSame(['2026-11'], $query['reporting_period']);
        $this->assertSame('0', $query['reported']);
        $this->get(route('reports.lists'))->assertOk()->assertSee('Listados de registros');
    }

    public function test_original_lists_remain_separate_with_real_counts_and_no_personal_data(): void
    {
        $reportCount = Report::count();
        $response = $this->get(route('reports.lists'))->assertOk()->assertViewHas('reports', fn ($rows) => $rows->total() === 4)
            ->assertSee('Ver listado')->assertSee('Lugar compartido')->assertDontSee('Lugar de otro equipo')
            ->assertDontSee('PERSONA PRIVADA')->assertDontSee('V12345678')->assertDontSee('Continuar agregando beneficiarios');
        $rows = $response->viewData('reports')->getCollection()->keyBy('id');
        $this->assertTrue($rows->has($this->own->id));
        $this->assertTrue($rows->has($this->second->id));
        $this->assertSame(2, $rows[$this->own->id]->beneficiaries_count);
        $this->assertSame($reportCount, Report::count());
        $this->get(route('reports.show', $this->foreign))->assertForbidden();
    }

    public function test_filters_projects_period_dates_reported_and_search_are_applied(): void
    {
        $filters = ['project_ids' => [$this->own->proyecto_id], 'reporting_period' => ['2026-11'], 'state_id' => [$this->own->state_id],
            'from' => '2026-11-20', 'to' => '2026-11-20', 'user_id' => $this->actor->id, 'reported' => '0'];
        $this->get(route('reports.lists', $filters))->assertOk()->assertViewHas('reports', fn ($rows) => $rows->total() === 2);
        $this->get(route('reports.lists', ['q' => '#'.$this->shared->id]))->assertOk()
            ->assertViewHas('reports', fn ($rows) => $rows->modelKeys() === [$this->shared->id]);
        $this->get(route('reports.lists', ['q' => 'otro equipo']))->assertOk()->assertViewHas('reports', fn ($rows) => $rows->isEmpty());
        $this->own->beneficiaries()->update(['reported_at' => today(), 'reported' => true]);
        $this->get(route('reports.lists', ['reported' => '1']))->assertOk()->assertViewHas('reports', fn ($rows) => $rows->modelKeys() === [$this->own->id]);
        $this->get(route('reports.lists', ['q' => $this->otherProject->proyecto->codigo]))->assertOk()
            ->assertViewHas('reports', fn ($rows) => $rows->modelKeys() === [$this->otherProject->id]);
    }

    public function test_permissions_control_access_and_detail_buttons_without_granting_editing(): void
    {
        $this->actor->revokePermissionTo('ver detalle de registros');
        $this->get(route('reports.lists'))->assertOk()->assertDontSee('>Ver listado</a>', false)->assertSee('Sin permiso de detalle');
        $this->get(route('reports.show', $this->own))->assertForbidden();
        $this->get(route('reports.resume', $this->own))->assertForbidden();
        $this->actor->revokePermissionTo('solo ver registros');
        $this->get(route('reports.lists'))->assertForbidden();
        $this->get(route('reports.index'))->assertForbidden();
    }

    public function test_group_deactivation_removes_peer_lists_and_administrators_keep_global_scope(): void
    {
        $this->group->update(['is_active' => false]);
        $this->get(route('reports.lists'))->assertOk()->assertViewHas('reports', fn ($rows) => $rows->total() === 3)->assertDontSee('Lugar compartido');
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('reports.lists'))->assertOk()->assertViewHas('reports', fn ($rows) => $rows->total() === 5)->assertSee('Lugar de otro equipo');
    }

    public function test_lists_are_paginated_and_invalid_filters_are_rejected(): void
    {
        for ($i = 0; $i < 21; $i++) $this->listing();
        $filters = ['project_ids' => [$this->own->proyecto_id], 'reporting_period' => ['2026-11']];
        $this->get(route('reports.lists', $filters))->assertOk()->assertViewHas('reports', fn ($rows) => $rows->total() === 23 && $rows->count() === 20);
        $this->get(route('reports.lists', $filters + ['page' => 2]))->assertOk()->assertViewHas('reports', fn ($rows) => $rows->count() === 3);
        foreach ([['project_ids' => [999999]], ['reported' => 'invalid'], ['q' => str_repeat('a', 201)],
            ['from' => '2026-11-21', 'to' => '2026-11-20'], ['reporting_period' => ['2026-13']]] as $invalid) {
            $this->getJson(route('reports.lists', $invalid))->assertUnprocessable();
        }
    }
}
