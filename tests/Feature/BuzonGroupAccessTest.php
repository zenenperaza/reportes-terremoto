<?php

namespace Tests\Feature;

use App\Models\{BuzonEntry, Donante, Proyecto, User, UserGroup};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BuzonGroupAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;
    private User $peer;
    private User $outsider;
    private UserGroup $group;
    private Proyecto $project;
    private Proyecto $otherProject;
    private BuzonEntry $own;
    private BuzonEntry $shared;
    private BuzonEntry $foreign;
    private BuzonEntry $orphan;
    private BuzonEntry $otherProjectEntry;

    protected function setUp(): void
    {
        parent::setUp();
        $donor = Donante::create(['nombre' => 'Donante de prueba', 'estatus' => true]);
        $this->project = Proyecto::create(['donante_id' => $donor->id, 'codigo' => 'GROUP-A', 'descripcion' => 'Proyecto A', 'estatus' => true]);
        $this->otherProject = Proyecto::create(['donante_id' => $donor->id, 'codigo' => 'GROUP-B', 'descripcion' => 'Proyecto B', 'estatus' => true]);
        $this->actor = User::factory()->create(['role' => 'reporter']);
        $this->peer = User::factory()->create(['role' => 'reporter']);
        $this->outsider = User::factory()->create(['role' => 'reporter']);
        $this->actor->givePermissionTo(['ver buzon', 'crear buzon', 'editar buzon', 'eliminar buzon']);
        foreach ([$this->actor, $this->peer, $this->outsider] as $user) {
            $user->projects()->attach($this->project);
        }
        $this->group = UserGroup::create(['name' => 'Equipo A', 'is_active' => true, 'allow_member_editing' => false]);
        $this->group->users()->attach([$this->actor->id, $this->peer->id]);
        $this->own = $this->entry($this->actor, ['full_name' => 'OPINION_PROPIA']);
        $this->shared = $this->entry($this->peer, ['full_name' => 'OPINION_COMPANERO', 'opinion' => 'bad']);
        $this->foreign = $this->entry($this->outsider, ['full_name' => 'OPINION_OTRO_EQUIPO', 'opinion' => 'bad']);
        $this->orphan = $this->entry(null, ['full_name' => 'AUTOR_DESCONOCIDO']);
        $this->otherProjectEntry = $this->entry($this->peer, ['proyecto_id' => $this->otherProject->id, 'full_name' => 'OTRO_PROYECTO']);
        $this->actingAs($this->actor);
    }

    private function payload(array $changes = []): array
    {
        return array_replace(['proyecto_id' => $this->project->id, 'opinion_date' => today()->toDateString(),
            'place_name' => 'Lugar', 'opinion' => 'very_good', 'feedback_type' => 'comment'], $changes);
    }

    private function entry(?User $author, array $changes = []): BuzonEntry
    {
        return BuzonEntry::create($this->payload($changes) + ['created_by' => $author?->id]);
    }

    private function save(BuzonEntry $entry, array $changes = [])
    {
        return $this->putJson(route('buzon.update', $entry), $this->payload(['version' => $entry->fresh()->version] + $changes));
    }

    public function test_list_counts_filters_and_direct_urls_show_only_own_and_direct_team_entries_in_assigned_projects(): void
    {
        $page = $this->get(route('buzon.index'))->assertOk()->assertSee('OPINION_PROPIA')->assertSee('OPINION_COMPANERO')
            ->assertDontSee('OPINION_OTRO_EQUIPO')->assertDontSee('AUTOR_DESCONOCIDO')->assertDontSee('OTRO_PROYECTO');
        $this->assertEqualsCanonicalizing([$this->own->id, $this->shared->id], $page->viewData('entries')->modelKeys());
        $this->assertSame(2, (int) $page->viewData('counts')->sum());
        $this->get(route('buzon.index', ['opinion' => 'bad', 'project_ids' => [$this->project->id],
            'from' => today()->toDateString(), 'to' => today()->toDateString()]))->assertOk()
            ->assertViewHas('entries', fn ($entries) => $entries->modelKeys() === [$this->shared->id])
            ->assertViewHas('counts', fn ($counts) => (int) $counts->sum() === 1);
        foreach ([$this->foreign, $this->orphan, $this->otherProjectEntry] as $entry) {
            $this->get(route('buzon.show', $entry))->assertNotFound();
            $this->get(route('buzon.edit', $entry))->assertNotFound();
            $this->save($entry)->assertForbidden();
            $this->deleteJson(route('buzon.destroy', $entry), ['version' => 1])->assertNotFound();
        }
        $this->assertDatabaseCount('buzon_entries', 5);
    }

    public function test_without_cross_editing_peer_is_read_only_and_own_entry_can_be_edited_and_deleted(): void
    {
        $page = $this->get(route('buzon.index'))->assertOk();
        $page->assertSee('href="'.route('buzon.edit', $this->own).'"', false)
            ->assertDontSee('href="'.route('buzon.edit', $this->shared).'"', false)
            ->assertSee('action="'.route('buzon.destroy', $this->own).'"', false)
            ->assertDontSee('action="'.route('buzon.destroy', $this->shared).'"', false);
        $this->get(route('buzon.show', $this->shared))->assertOk()->assertDontSee('Editar formulario')
            ->assertDontSee('data-buzon-delete', false);
        $this->get(route('buzon.edit', $this->shared))->assertForbidden();
        $this->save($this->shared, ['comment' => 'No autorizado'])->assertForbidden();
        $this->deleteJson(route('buzon.destroy', $this->shared), ['version' => 1])->assertForbidden();
        $this->save($this->own, ['comment' => 'Propia corregida'])->assertRedirect();
        $this->assertSame('Propia corregida', $this->own->fresh()->comment);
        $this->delete(route('buzon.destroy', $this->own), ['version' => 2])->assertRedirect();
        $this->assertDatabaseHas('buzon_entries', ['id' => $this->shared->id, 'version' => 1]);
    }

    public function test_cross_editing_with_action_permission_applies_to_reporters_coordinators_and_custom_roles(): void
    {
        $this->group->update(['allow_member_editing' => true]);
        Role::findOrCreate('editor-buzon', 'web');
        foreach (['reporter', 'coordinator', 'editor-buzon'] as $role) {
            $this->actor->update(['role' => $role]);
            $this->actingAs($this->actor->fresh());
            $this->get(route('buzon.show', $this->shared))->assertOk()->assertSee('Editar formulario')->assertSee('data-buzon-delete', false);
            $this->get(route('buzon.edit', $this->shared))->assertOk();
            $this->save($this->shared, ['comment' => 'Corregido por '.$role, 'created_by' => $this->actor->id])->assertRedirect();
            $this->assertSame($this->peer->id, $this->shared->fresh()->created_by);
            $this->assertSame('Corregido por '.$role, $this->shared->fresh()->comment);
        }
        $this->delete(route('buzon.destroy', $this->shared), ['version' => $this->shared->fresh()->version])->assertRedirect();
        $this->assertDatabaseMissing('buzon_entries', ['id' => $this->shared->id]);
    }

    public function test_enabled_cross_editing_never_grants_missing_action_permissions(): void
    {
        $this->group->update(['allow_member_editing' => true]);
        $this->actor->syncPermissions(['ver buzon']);
        $this->actingAs($this->actor->fresh());
        $this->get(route('buzon.show', $this->shared))->assertOk()->assertDontSee('Editar formulario')
            ->assertDontSee('data-buzon-delete', false);
        $this->get(route('buzon.edit', $this->shared))->assertForbidden();
        $this->save($this->shared)->assertForbidden();
        $this->deleteJson(route('buzon.destroy', $this->shared), ['version' => 1])->assertForbidden();
        $this->actor->givePermissionTo('editar buzon');
        $this->actingAs($this->actor->fresh());
        $this->save($this->shared)->assertRedirect();
        $this->deleteJson(route('buzon.destroy', $this->shared), ['version' => 2])->assertForbidden();
    }

    public function test_open_form_cannot_save_or_delete_after_cross_editing_or_membership_is_revoked(): void
    {
        $this->group->update(['allow_member_editing' => true]);
        $this->get(route('buzon.edit', $this->shared))->assertOk();
        $this->group->update(['allow_member_editing' => false]);
        $this->save($this->shared)->assertForbidden();
        $this->deleteJson(route('buzon.destroy', $this->shared), ['version' => 1])->assertForbidden();
        $this->group->update(['allow_member_editing' => true]);
        $this->group->users()->detach($this->peer);
        $this->get(route('buzon.show', $this->shared))->assertNotFound();
        $this->get(route('buzon.edit', $this->shared))->assertNotFound();
        $this->save($this->shared)->assertForbidden();
        $this->deleteJson(route('buzon.destroy', $this->shared), ['version' => 1])->assertNotFound();
        $this->assertSame(1, $this->shared->fresh()->version);
    }

    public function test_inactive_groups_cannot_share_entries_but_own_records_remain_available(): void
    {
        $this->group->update(['allow_member_editing' => true, 'is_active' => false]);
        $this->get(route('buzon.index'))->assertOk()->assertViewHas('entries', fn ($entries) => $entries->modelKeys() === [$this->own->id]);
        $this->get(route('buzon.edit', $this->own))->assertOk();
        $this->get(route('buzon.show', $this->shared))->assertNotFound();
        $this->save($this->shared)->assertForbidden();
        $this->deleteJson(route('buzon.destroy', $this->shared), ['version' => 1])->assertNotFound();
    }

    public function test_multiple_groups_are_direct_not_transitive_and_the_enabled_group_must_be_shared(): void
    {
        $unshared = UserGroup::create(['name' => 'Grupo del compañero', 'is_active' => true, 'allow_member_editing' => true]);
        $unshared->users()->attach([$this->peer->id, $this->outsider->id]);
        $this->get(route('buzon.index'))->assertOk()->assertDontSee('OPINION_OTRO_EQUIPO');
        $this->save($this->shared)->assertForbidden();
        $second = UserGroup::create(['name' => 'Segundo grupo directo', 'is_active' => true, 'allow_member_editing' => true]);
        $second->users()->attach([$this->actor->id, $this->outsider->id]);
        $this->get(route('buzon.index'))->assertOk()->assertViewHas('entries', fn ($entries) => $entries->total() === 3);
        $this->get(route('buzon.edit', $this->foreign))->assertOk();
        $this->get(route('buzon.edit', $this->shared))->assertForbidden();
        $this->group->update(['allow_member_editing' => true]);
        $this->get(route('buzon.edit', $this->shared))->assertOk();
    }

    public function test_countrywide_access_without_a_group_does_not_expose_other_authors_and_projects_still_restrict(): void
    {
        $this->actor->update(['role' => 'coordinator', 'countrywide_access' => true]);
        $this->actor->userGroups()->detach();
        $this->actingAs($this->actor->fresh())->get(route('buzon.index'))->assertOk()
            ->assertViewHas('entries', fn ($entries) => $entries->modelKeys() === [$this->own->id]);
        $this->get(route('buzon.show', $this->shared))->assertNotFound();
        $this->group->users()->attach($this->actor);
        $this->actor->projects()->attach($this->otherProject);
        $this->get(route('buzon.show', $this->otherProjectEntry))->assertOk();
        $this->actor->projects()->detach($this->project);
        $this->get(route('buzon.show', $this->own))->assertNotFound();
        $this->get(route('buzon.index'))->assertOk()->assertViewHas('entries', fn ($entries) => $entries->modelKeys() === [$this->otherProjectEntry->id]);
    }

    public function test_admin_keeps_global_scope_but_must_still_have_each_action_permission(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('buzon.index'))->assertOk()->assertViewHas('entries', fn ($entries) => $entries->total() === 5);
        $this->get(route('buzon.edit', $this->orphan))->assertOk();
        $this->save($this->foreign, ['comment' => 'Corrección administrativa'])->assertRedirect();
        $admin->syncRoles([]);
        $admin->syncPermissions(['ver buzon']);
        $this->actingAs($admin->fresh())->get(route('buzon.show', $this->foreign))->assertOk()->assertDontSee('Editar formulario');
        $this->save($this->foreign)->assertForbidden();
        $this->deleteJson(route('buzon.destroy', $this->foreign), ['version' => 2])->assertForbidden();
    }

    public function test_creation_records_real_author_and_does_not_allow_spoofing_to_another_team(): void
    {
        $this->post(route('buzon.store'), $this->payload(['created_by' => $this->outsider->id, 'full_name' => null]))
            ->assertSessionHasNoErrors()->assertRedirect();
        $created = BuzonEntry::latest('id')->firstOrFail();
        $this->assertSame($this->actor->id, $created->created_by);
        $this->assertNull($created->full_name);
        $this->get(route('buzon.show', $created))->assertOk();
        $this->assertDatabaseCount('beneficiaries', 0);
        $this->assertDatabaseCount('reports', 0);
    }
}
