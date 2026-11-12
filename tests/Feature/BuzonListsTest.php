<?php

namespace Tests\Feature;

use App\Models\{AuditLog, BuzonEntry, BuzonList, Donante, Proyecto, User, UserGroup};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BuzonListsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $peer;
    private User $outsider;
    private Proyecto $project;
    private Proyecto $other;
    private UserGroup $group;

    protected function setUp(): void
    {
        parent::setUp();
        $donor = Donante::create(['nombre' => 'Donante prueba', 'estatus' => true]);
        $this->project = Proyecto::create(['donante_id' => $donor->id, 'codigo' => 'LIST-A', 'descripcion' => 'Proyecto A', 'estatus' => true]);
        $this->other = Proyecto::create(['donante_id' => $donor->id, 'codigo' => 'LIST-B', 'descripcion' => 'Proyecto B', 'estatus' => true]);
        foreach (['owner', 'peer', 'outsider'] as $field) {
            $this->{$field} = User::factory()->create(['role' => 'reporter']);
            $this->{$field}->givePermissionTo(['ver buzon', 'crear buzon', 'editar buzon', 'eliminar buzon']);
            $this->{$field}->projects()->attach([$this->project->id, $this->other->id]);
        }
        $this->group = UserGroup::create(['name' => 'Equipo de prueba', 'is_active' => true, 'allow_member_editing' => false]);
        $this->group->users()->attach([$this->owner->id, $this->peer->id]);
        $this->actingAs($this->owner);
    }

    private function payload(array $changes = []): array
    {
        return array_replace(['proyecto_id' => $this->project->id, 'opinion_date' => today()->toDateString(),
            'place_name' => 'Lugar de prueba', 'opinion' => 'very_good', 'feedback_type' => 'comment'], $changes);
    }

    private function listing(?User $user = null, ?Proyecto $project = null): BuzonList
    {
        return BuzonList::create(['proyecto_id' => ($project ?? $this->project)->id, 'created_by' => ($user ?? $this->owner)->id]);
    }

    private function entry(BuzonList $list, ?User $user = null, array $changes = []): BuzonEntry
    {
        return $list->entries()->create($this->payload(['proyecto_id' => $list->proyecto_id] + $changes) + ['created_by' => ($user ?? $this->owner)->id]);
    }

    private function append(BuzonList $list, array $changes = [])
    {
        return $this->postJson(route('buzon.store'), $this->payload(['buzon_list_id' => $list->id] + $changes));
    }

    public function test_first_save_creates_a_list_and_next_opinions_appear_below_the_clean_form(): void
    {
        $this->get(route('buzon.create'))->assertOk()->assertSee('Al guardar la primera opinión');
        $this->assertDatabaseCount('buzon_lists', 0);
        $response = $this->post(route('buzon.store'), $this->payload(['full_name' => 'PRIMERA_PERSONA_PRUEBA']));
        $list = BuzonList::sole();
        $url = route('buzon.create', ['proyecto_id' => $this->project->id, 'list_id' => $list->id]);
        $response->assertRedirect($url);
        $this->assertSame($this->owner->id, $list->created_by);
        $this->append($list, ['full_name' => 'SEGUNDA_PERSONA_PRUEBA'])->assertRedirect($url);
        $page = $this->get($url)->assertOk()->assertSee('PRIMERA_PERSONA_PRUEBA')->assertSee('SEGUNDA_PERSONA_PRUEBA')
            ->assertViewHas('listEntries', fn ($rows) => $rows->total() === 2)
            ->assertViewHas('entry', fn ($entry) => ! $entry->exists)
            ->assertSeeInOrder(['Guardar formulario del Buzón', 'id="buzon-list-entries"'], false)
            ->assertSee('Iniciar nuevo listado')->assertSee('Editar')->assertSee('data-buzon-delete', false);
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$page->getContent());
        $xpath = new \DOMXPath($dom);
        $this->assertSame('', $xpath->evaluate('string(//form[@data-buzon-entry]//input[@name="full_name"]/@value)'));
        $this->assertSame((string) $list->id, $xpath->evaluate('string(//form[@data-buzon-entry]//input[@name="buzon_list_id"]/@value)'));
        $this->assertSame(0, $xpath->query('//form[@data-buzon-entry]//form')->length);
        $this->get($url)->assertOk();
        $this->assertDatabaseCount('buzon_lists', 1);
        $this->assertDatabaseCount('buzon_entries', 2);
        $this->assertDatabaseCount('reports', 0);
        $this->assertDatabaseCount('beneficiaries', 0);
    }

    public function test_lists_survive_session_end_and_can_be_resumed_without_creating_another_list(): void
    {
        $list = $this->listing();
        $entry = $this->entry($list);
        session()->flush();
        $this->app['auth']->forgetGuards();
        $this->actingAs($this->owner->fresh());
        $this->get(route('buzon.lists'))->assertOk()->assertViewHas('lists', fn ($lists) => $lists->modelKeys() === [$list->id]);
        $this->get(route('buzon.lists.show', $list))->assertOk()->assertSee('Agregar opiniones')
            ->assertViewHas('listEntries', fn ($rows) => $rows->modelKeys() === [$entry->id]);
        $this->get(route('buzon.create', ['list_id' => $list->id]))->assertOk()->assertViewHas('selectedProject', $this->project->id);
        $this->append($list)->assertRedirect();
        $this->assertDatabaseCount('buzon_lists', 1);
        $this->assertSame(2, $list->entries()->count());
    }

    public function test_starting_another_list_never_merges_history_and_a_failed_first_save_creates_nothing(): void
    {
        $historical = BuzonEntry::create($this->payload() + ['created_by' => $this->owner->id]);
        $this->postJson(route('buzon.store'), $this->payload(['opinion' => 'invalid']))->assertUnprocessable();
        $this->assertDatabaseCount('buzon_lists', 0);
        $this->post(route('buzon.store'), $this->payload())->assertRedirect();
        $firstList = BuzonList::sole();
        $this->get(route('buzon.create', ['proyecto_id' => $this->project->id]))->assertOk()->assertViewHas('listing', null);
        $this->post(route('buzon.store'), $this->payload())->assertRedirect();
        $this->assertDatabaseCount('buzon_lists', 2);
        $this->assertNull($historical->fresh()->buzon_list_id);
        $this->assertSame(1, $firstList->entries()->count());
        $this->get(route('buzon.show', $historical))->assertOk();
        $this->get(route('buzon.index'))->assertOk()->assertViewHas('entries', fn ($rows) => $rows->total() === 3);
    }

    public function test_edit_and_delete_return_to_the_same_list_and_empty_lists_can_be_reused(): void
    {
        $list = $this->listing();
        $entry = $this->entry($list);
        $this->put(route('buzon.update', $entry), $this->payload(['version' => 1, 'comment' => 'Corrección de prueba']))
            ->assertRedirect(route('buzon.lists.show', $list));
        $this->assertSame('Corrección de prueba', $entry->fresh()->comment);
        $this->putJson(route('buzon.update', $entry), $this->payload(['version' => 1]))->assertUnprocessable();
        $this->deleteJson(route('buzon.destroy', $entry), ['version' => 1])->assertUnprocessable();
        $this->delete(route('buzon.destroy', $entry), ['version' => 2])->assertRedirect(route('buzon.lists.show', $list));
        $this->get(route('buzon.lists.show', $list))->assertOk()->assertViewHas('listEntries', fn ($rows) => $rows->total() === 0);
        $this->append($list)->assertRedirect();
        $this->assertDatabaseCount('buzon_lists', 1);
        $this->assertDatabaseCount('buzon_entries', 1);
    }

    public function test_list_project_and_membership_cannot_be_changed_or_forged(): void
    {
        $list = $this->listing();
        $entry = $this->entry($list);
        $other = $this->listing($this->owner, $this->other);
        $this->append($list, ['proyecto_id' => $this->other->id])->assertUnprocessable()->assertJsonValidationErrors('proyecto_id');
        $this->putJson(route('buzon.update', $entry), $this->payload(['version' => 1, 'buzon_list_id' => $other->id]))
            ->assertUnprocessable()->assertJsonValidationErrors('buzon_list_id');
        $this->putJson(route('buzon.update', $entry), $this->payload(['version' => 1, 'proyecto_id' => $this->other->id]))
            ->assertUnprocessable()->assertJsonValidationErrors('proyecto_id');
        $this->assertSame($list->id, $entry->fresh()->buzon_list_id);
        $this->append($list, ['created_by' => $this->outsider->id])->assertRedirect();
        $this->assertSame($this->owner->id, $list->entries()->latest('id')->firstOrFail()->created_by);
        $this->assertDatabaseCount('buzon_lists', 2);
        $this->assertDatabaseCount('buzon_entries', 2);
    }

    public function test_team_can_view_but_cannot_append_edit_or_delete_until_cross_editing_is_enabled(): void
    {
        $list = $this->listing($this->peer);
        $entry = $this->entry($list, $this->peer);
        $this->get(route('buzon.lists.show', $list))->assertOk()->assertViewHas('canAppendList', false)
            ->assertDontSee('Agregar opiniones')->assertDontSee('data-buzon-delete', false)->assertDontSee('>Editar<', false);
        $this->get(route('buzon.create', ['list_id' => $list->id]))->assertNotFound();
        $this->append($list)->assertForbidden();
        $this->putJson(route('buzon.update', $entry), $this->payload(['version' => 1]))->assertForbidden();
        $this->deleteJson(route('buzon.destroy', $entry), ['version' => 1])->assertForbidden();
        $this->group->update(['allow_member_editing' => true]);
        $this->get(route('buzon.lists.show', $list))->assertOk()->assertViewHas('canAppendList', true)->assertSee('Agregar opiniones');
        $this->append($list)->assertRedirect();
        $this->assertSame($this->owner->id, $list->entries()->latest('id')->firstOrFail()->created_by);
        $this->put(route('buzon.update', $entry), $this->payload(['version' => 1]))->assertRedirect();
        $this->delete(route('buzon.destroy', $entry), ['version' => 2])->assertRedirect();
        $this->assertSame($this->peer->id, $list->fresh()->created_by);
    }

    public function test_revoked_cross_editing_or_group_membership_blocks_stale_forms(): void
    {
        $list = $this->listing($this->peer);
        $this->group->update(['allow_member_editing' => true]);
        $this->get(route('buzon.create', ['list_id' => $list->id]))->assertOk();
        $this->group->update(['allow_member_editing' => false]);
        $this->append($list)->assertForbidden();
        $this->group->update(['allow_member_editing' => true, 'is_active' => false]);
        $this->get(route('buzon.lists.show', $list))->assertNotFound();
        $this->append($list)->assertForbidden();
        $this->assertSame(0, $list->entries()->count());
    }

    public function test_list_scope_and_totals_never_expose_other_teams_or_unassigned_projects(): void
    {
        $own = $this->listing();
        $shared = $this->listing($this->peer);
        $foreign = $this->listing($this->outsider);
        $this->entry($own);
        $this->entry($shared, $this->peer);
        $this->entry($shared, $this->outsider, ['full_name' => 'PRIVATE_OTHER_TEAM']);
        $this->entry($foreign, $this->outsider, ['full_name' => 'PRIVATE_FOREIGN_LIST']);
        $page = $this->get(route('buzon.lists'))->assertOk();
        $this->assertEqualsCanonicalizing([$own->id, $shared->id], $page->viewData('lists')->modelKeys());
        $this->assertSame(1, $page->viewData('lists')->firstWhere('id', $shared->id)->entries_count);
        $this->get(route('buzon.lists.show', $shared))->assertOk()->assertDontSee('PRIVATE_OTHER_TEAM')
            ->assertViewHas('listEntries', fn ($rows) => $rows->total() === 1);
        $this->get(route('buzon.lists.show', $foreign))->assertNotFound()->assertDontSee('PRIVATE_FOREIGN_LIST');
        $this->append($foreign)->assertForbidden();
        $this->owner->projects()->detach($this->project);
        $this->get(route('buzon.lists'))->assertOk()->assertViewHas('lists', fn ($rows) => $rows->isEmpty());
        $this->get(route('buzon.lists.show', $own))->assertNotFound();
        $this->append($own)->assertForbidden();
    }

    public function test_create_only_can_continue_own_list_but_never_read_previous_opinions_or_continue_team_list(): void
    {
        $list = $this->listing();
        $this->entry($list, $this->owner, ['full_name' => 'PRIVATE_PREVIOUS_PERSON']);
        $shared = $this->listing($this->peer);
        $this->group->update(['allow_member_editing' => true]);
        $this->owner->syncPermissions(['crear buzon']);
        $this->actingAs($this->owner->fresh());
        $this->get(route('buzon.create', ['list_id' => $list->id]))->assertOk()->assertViewHas('canViewList', false)
            ->assertDontSee('PRIVATE_PREVIOUS_PERSON')->assertDontSee('id="buzon-list-entries"', false);
        $this->append($list)->assertRedirect();
        $this->get(route('buzon.lists'))->assertForbidden();
        $this->get(route('buzon.lists.show', $list))->assertForbidden();
        $this->get(route('buzon.create', ['list_id' => $shared->id]))->assertNotFound();
        $this->append($shared)->assertForbidden();
    }

    public function test_action_permissions_remain_independent_and_revocation_applies_to_list_actions(): void
    {
        $list = $this->listing();
        $entry = $this->entry($list);
        $this->owner->syncPermissions(['ver buzon']);
        $this->actingAs($this->owner->fresh());
        $this->get(route('buzon.lists.show', $list))->assertOk()->assertDontSee('Agregar opiniones')
            ->assertDontSee('>Editar<', false)->assertDontSee('data-buzon-delete', false);
        $this->get(route('buzon.create', ['list_id' => $list->id]))->assertForbidden();
        $this->append($list)->assertForbidden();
        $this->putJson(route('buzon.update', $entry), $this->payload(['version' => 1]))->assertForbidden();
        $this->deleteJson(route('buzon.destroy', $entry), ['version' => 1])->assertForbidden();
    }

    public function test_inactive_projects_keep_history_but_cannot_receive_new_opinions(): void
    {
        $list = $this->listing();
        $entry = $this->entry($list);
        $this->project->update(['estatus' => false]);
        $this->get(route('buzon.lists.show', $list))->assertOk()->assertViewHas('canAppendList', false);
        $this->get(route('buzon.create', ['list_id' => $list->id]))->assertNotFound();
        $this->append($list)->assertForbidden();
        $this->put(route('buzon.update', $entry), $this->payload(['version' => 1]))->assertRedirect();
        $this->assertDatabaseCount('buzon_entries', 1);
    }

    public function test_project_filters_private_headers_and_audit_redaction_apply_to_lists(): void
    {
        $first = $this->listing();
        $second = $this->listing($this->owner, $this->other);
        $this->get(route('buzon.lists', ['project_ids' => [$this->other->id]]))->assertOk()
            ->assertViewHas('lists', fn ($rows) => $rows->modelKeys() === [$second->id])
            ->assertHeader('Cache-Control', 'no-store, private')->assertHeader('Referrer-Policy', 'same-origin');
        $this->get(route('buzon.lists.show', $first))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->append($first, ['comment' => 'PRIVATE_COMMENT'])->assertRedirect();
        $logs = AuditLog::where('route_name', 'like', 'buzon.%')->get();
        foreach ($logs as $log) $this->assertNull($log->request_payload);
        $this->assertStringNotContainsString('PRIVATE_COMMENT', $logs->toJson());
        foreach (['invalid', ['invalid'], -1, 999999] as $id) {
            $this->getJson(route('buzon.create', ['list_id' => $id]))->assertStatus(is_int($id) && $id > 0 ? 404 : 422);
        }
    }

    public function test_administrators_keep_global_scope_but_still_need_action_permissions(): void
    {
        $list = $this->listing($this->outsider);
        $this->entry($list, $this->outsider);
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('buzon.lists.show', $list))->assertOk()->assertViewHas('canAppendList', true);
        $this->append($list)->assertRedirect();
        Role::findByName('admin', 'web')->revokePermissionTo(['ver buzon', 'crear buzon']);
        $this->actingAs($admin->fresh())->get(route('buzon.lists'))->assertForbidden();
        $this->get(route('buzon.lists.show', $list))->assertForbidden();
        $this->append($list)->assertForbidden();
    }

    public function test_project_with_an_empty_saved_list_is_not_deleted(): void
    {
        $list = $this->listing();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->delete(route('proyectos.destroy', $this->project))->assertSessionHas('error');
        $this->assertDatabaseHas('proyectos', ['id' => $this->project->id]);
        $this->assertDatabaseHas('buzon_lists', ['id' => $list->id]);
    }
}
