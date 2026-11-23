<?php

namespace Tests\Feature;

use App\Models\{BuzonEntry, Donante, Proyecto, User, UserGroup};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BuzonDataTableTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;
    private User $peer;
    private UserGroup $group;
    private Proyecto $project;
    private Proyecto $otherProject;
    private BuzonEntry $own;
    private BuzonEntry $shared;
    private BuzonEntry $foreign;

    protected function setUp(): void
    {
        parent::setUp();
        $donor = Donante::create(['nombre' => 'Donante de pruebas', 'estatus' => true]);
        $this->project = Proyecto::create(['donante_id' => $donor->id, 'codigo' => 'DT-A', 'descripcion' => 'Proyecto A', 'estatus' => true]);
        $this->otherProject = Proyecto::create(['donante_id' => $donor->id, 'codigo' => 'DT-B', 'descripcion' => 'Proyecto B', 'estatus' => true]);
        $this->actor = User::factory()->create(['role' => 'reporter']);
        $this->peer = User::factory()->create(['role' => 'reporter']);
        $outsider = User::factory()->create(['role' => 'reporter']);
        $this->actor->syncRoles([]);
        $this->actor->givePermissionTo(['ver buzon', 'eliminar buzon']);
        foreach ([$this->actor, $this->peer, $outsider] as $user) $user->projects()->attach($this->project);
        $this->group = UserGroup::create(['name' => 'Equipo de prueba', 'is_active' => true, 'allow_member_editing' => false]);
        $this->group->users()->attach([$this->actor->id, $this->peer->id]);
        $this->own = $this->entry($this->actor, ['full_name' => 'OPINION_PROPIA']);
        $this->shared = $this->entry($this->peer, ['full_name' => 'OPINION_COMPANERO', 'opinion' => 'bad']);
        $this->foreign = $this->entry($outsider, ['full_name' => 'OTRO_EQUIPO']);
        $this->entry($this->peer, ['proyecto_id' => $this->otherProject->id, 'full_name' => 'OTRO_PROYECTO']);
        $this->actingAs($this->actor);
    }

    private function entry(User $author, array $changes = []): BuzonEntry
    {
        return BuzonEntry::create(array_replace(['proyecto_id' => $this->project->id, 'created_by' => $author->id,
            'opinion_date' => '2026-11-20', 'place_name' => 'Comunidad', 'opinion' => 'very_good',
            'feedback_type' => 'comment', 'comment' => 'Texto reservado', 'phone' => '04120000000'], $changes));
    }

    private function data(array $changes = [])
    {
        return $this->getJson(route('buzon.index', array_replace(['draw' => 3, 'start' => 0, 'length' => 20], $changes)));
    }

    public function test_table_is_paginated_searchable_and_never_leaks_other_groups_or_projects(): void
    {
        $response = $this->data(['length' => 1])->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('draw', 3)->assertJsonPath('recordsTotal', 2)->assertJsonPath('recordsFiltered', 2)->assertJsonCount(1, 'data');
        $this->assertSame('OPINION_COMPANERO', $response->json('data.0.full_name'));
        $this->data(['start' => 1, 'length' => 1])->assertOk()->assertJsonPath('data.0.full_name', 'OPINION_PROPIA');
        $this->data(['search' => ['value' => 'PROPIA']])->assertOk()->assertJsonPath('recordsFiltered', 1)->assertJsonPath('data.0.full_name', 'OPINION_PROPIA');
        foreach (['OTRO_EQUIPO', 'OTRO_PROYECTO'] as $search) {
            $this->data(['search' => ['value' => $search]])->assertOk()->assertJsonPath('recordsFiltered', 0)->assertJsonCount(0, 'data');
        }
        $this->data()->assertDontSee('04120000000')->assertDontSee('Texto reservado')->assertDontSee('public_submission_key');
        $this->data(['project_ids' => [$this->otherProject->id]])->assertUnprocessable();
    }

    public function test_export_requires_its_permission_and_returns_all_matching_pages_without_action_forms(): void
    {
        $this->data(['export_type' => 'excel'])->assertForbidden();
        $this->data(['export_type' => 'pdf'])->assertForbidden();
        $this->actor->givePermissionTo('exportar buzon');
        for ($i = 0; $i < 30; $i++) $this->entry($this->actor, ['full_name' => 'CARGA '.$i]);
        foreach (['excel', 'pdf'] as $format) {
            $response = $this->data(['length' => 1, 'start' => 25, 'export_type' => $format, 'search' => ['value' => 'CARGA'],
                'order' => [['column' => 5, 'dir' => 'asc']]])->assertOk()->assertJsonPath('recordsFiltered', 30)->assertJsonCount(30, 'data');
            $this->assertArrayNotHasKey('actions', $response->json('data.0'));
            $this->assertSame('CARGA 0', $response->json('data.0.full_name'));
        }
        $this->actor->revokePermissionTo('ver buzon');
        $this->data(['export_type' => 'excel'])->assertForbidden();
    }

    public function test_export_reapplies_projects_dates_opinion_feedback_and_team_visibility(): void
    {
        $this->actor->givePermissionTo('exportar buzon');
        $filters = ['export_type' => 'pdf', 'project_ids' => [$this->project->id], 'from' => '2026-11-20',
            'to' => '2026-11-20', 'opinion' => 'bad', 'feedback_type' => 'comment'];
        $this->data($filters)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.full_name', 'OPINION_COMPANERO');
        $this->data($filters + ['search' => ['value' => 'PROPIA']])->assertOk()->assertJsonCount(0, 'data');
        $this->group->update(['is_active' => false]);
        $this->data($filters)->assertOk()->assertJsonCount(0, 'data');
        $this->data(['from' => '2026-11-21', 'to' => '2026-11-20'])->assertUnprocessable();
    }

    public function test_delete_buttons_and_endpoint_keep_permissions_cross_editing_and_version_checks(): void
    {
        $rows = collect($this->data()->assertOk()->json('data'))->keyBy('full_name');
        $this->assertStringContainsString('data-buzon-delete', $rows['OPINION_PROPIA']['actions']);
        $this->assertStringContainsString('name="version" value="1"', $rows['OPINION_PROPIA']['actions']);
        $this->assertStringNotContainsString('data-buzon-delete', $rows['OPINION_COMPANERO']['actions']);
        $this->deleteJson(route('buzon.destroy', $this->shared), ['version' => 1])->assertForbidden();
        $this->deleteJson(route('buzon.destroy', $this->foreign), ['version' => 1])->assertNotFound();
        $this->group->update(['allow_member_editing' => true]);
        $rows = collect($this->data()->assertOk()->json('data'))->keyBy('full_name');
        $this->assertStringContainsString('data-buzon-delete', $rows['OPINION_COMPANERO']['actions']);
        $this->shared->update(['version' => 2]);
        $this->deleteJson(route('buzon.destroy', $this->shared), ['version' => 1])->assertUnprocessable();
        $this->deleteJson(route('buzon.destroy', $this->shared), ['version' => 2])->assertRedirect();
        $this->actor->revokePermissionTo('eliminar buzon');
        $this->assertStringNotContainsString('data-buzon-delete', $this->data()->json('data.0.actions'));
        $this->deleteJson(route('buzon.destroy', $this->own), ['version' => 1])->assertForbidden();
    }

    public function test_labels_origin_and_dates_are_searchable_and_public_entries_use_the_link_owner_scope(): void
    {
        $external = $this->entry($this->actor, ['full_name' => 'EXTERNA', 'feedback_type' => 'request', 'opinion_date' => '2026-11-21']);
        $external->forceFill(['created_by' => null, 'submitted_via_public_link' => true, 'public_recipient_id' => $this->actor->id])->save();
        foreach (['Solicitud', 'externo', '21/11/2026'] as $search) {
            $this->data(['search' => ['value' => $search]])->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.full_name', 'EXTERNA');
        }
        $this->data(['search' => ['value' => 'Mala']])->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.full_name', 'OPINION_COMPANERO');
    }

    public function test_export_and_delete_permissions_can_be_assigned_to_roles_independently(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('roles.edit', Role::findByName('reporter', 'web')))->assertOk()
            ->assertViewHas('permissions', fn ($permissions) => collect(['exportar buzon', 'eliminar buzon'])->diff($permissions->pluck('name'))->isEmpty());
        $this->assertTrue($admin->can('exportar buzon'));
        $this->assertFalse($this->actor->can('exportar buzon'));
        $role = Role::create(['name' => 'Consulta Buzon', 'guard_name' => 'web']);
        $role->givePermissionTo(['ver buzon', 'exportar buzon']);
        $this->actor->syncPermissions([]);
        $this->actor->assignRole($role);
        $this->actingAs($this->actor->fresh());
        $this->get(route('buzon.index'))->assertOk()->assertSee('data-can-export="1"', false)->assertSee('vendor/datatables/pdfmake.min.js', false);
        $this->data(['export_type' => 'excel'])->assertOk();
        $this->deleteJson(route('buzon.destroy', $this->own), ['version' => 1])->assertForbidden();
    }

    public function test_query_parameters_are_validated_and_table_assets_are_local(): void
    {
        foreach ([['length' => -1], ['length' => 101], ['start' => -1], ['export_type' => 'csv'],
            ['search' => ['value' => str_repeat('x', 201)]], ['order' => [['column' => 99, 'dir' => 'asc']]]] as $parameters) {
            $this->data($parameters)->assertUnprocessable();
        }
        $this->get(route('buzon.index'))->assertOk()->assertSee('id="buzon-table"', false)
            ->assertSee('js/buzon-table.js', false)->assertSee('vendor/datatables/dataTables.responsive.min.js', false)
            ->assertSee('data-can-export="0"', false)->assertDontSee('vendor/datatables/jszip.min.js', false);
    }

    public function test_sorting_uses_the_spanish_opinion_labels(): void
    {
        $this->entry($this->actor, ['opinion' => 'neutral']);
        $response = $this->data(['order' => [['column' => 3, 'dir' => 'asc']]])->assertOk();
        $this->assertSame(['Mala', 'Muy buena', 'Ni buena ni mala'], array_column($response->json('data'), 'opinion'));
    }
}
