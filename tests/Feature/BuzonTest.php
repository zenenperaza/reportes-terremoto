<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\BuzonEntry;
use App\Models\Donante;
use App\Models\Proyecto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BuzonTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Proyecto $first;
    private Proyecto $second;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($this->admin);
        $donor = Donante::create(['nombre' => 'Donante de prueba', 'estatus' => true]);
        $this->first = Proyecto::create(['donante_id' => $donor->id, 'codigo' => 'FIRST', 'descripcion' => 'Primer proyecto', 'estatus' => true]);
        $this->second = Proyecto::create(['donante_id' => $donor->id, 'codigo' => 'SECOND', 'descripcion' => 'Segundo proyecto', 'estatus' => true]);
    }

    private function payload(array $extra = []): array
    {
        return array_replace(['proyecto_id' => (string) $this->first->id, 'opinion_date' => today()->toDateString(),
            'place_name' => 'Comunidad de prueba', 'opinion' => 'very_good', 'feedback_type' => 'suggestion'], $extra);
    }

    private function entry(array $extra = []): BuzonEntry
    {
        return BuzonEntry::create($this->payload($extra) + ['created_by' => $this->admin->id]);
    }

    public function test_single_permission_is_granted_only_to_admin_initially_and_is_protected(): void
    {
        $permission = Permission::findByName('Buzon', 'web');
        $this->assertTrue($this->admin->can('Buzon'));
        foreach (['reporter', 'coordinator'] as $role) {
            $this->assertFalse(User::factory()->create(['role' => $role])->can('Buzon'));
        }
        $this->put(route('permissions.update', $permission), ['name' => 'Otro'])->assertSessionHas('error');
        $this->delete(route('permissions.destroy', $permission))->assertSessionHas('error');
        $this->assertDatabaseHas('permissions', ['name' => 'Buzon']);
    }

    public function test_users_and_admin_without_buzon_cannot_read_or_write_any_entry(): void
    {
        $entry = $this->entry(['full_name' => 'PERSONA RESERVADA', 'comment' => 'QUEJA RESERVADA']);
        foreach (['reporter', 'admin'] as $role) {
            if ($role === 'admin') Role::findByName('admin', 'web')->revokePermissionTo('Buzon');
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user);
            foreach (['buzon.index' => [], 'buzon.create' => [], 'buzon.show' => [$entry], 'buzon.edit' => [$entry]] as $route => $params) {
                $this->get(route($route, $params))->assertForbidden()->assertDontSee('PERSONA RESERVADA')->assertDontSee('QUEJA RESERVADA');
            }
            $this->postJson(route('buzon.store'), $this->payload())->assertForbidden();
            $this->putJson(route('buzon.update', $entry), $this->payload(['version' => 1]))->assertForbidden();
            $this->get(route('dashboard'))->assertOk()->assertDontSee('href="'.route('buzon.index').'"', false);
        }
        $this->assertDatabaseCount('buzon_entries', 1);
    }

    public function test_permission_works_for_every_project_without_indicator_or_beneficiary_configuration(): void
    {
        foreach ([$this->first, $this->second] as $project) {
            $this->get(route('buzon.create', ['proyecto_id' => $project->id]))->assertOk()->assertSee('Registrar opinión');
            $this->post(route('buzon.store'), $this->payload(['proyecto_id' => (string) $project->id]))
                ->assertSessionHasNoErrors()->assertRedirect();
            $this->get(route('proyectos.show', $project))->assertOk()->assertSee('Buzón del proyecto');
        }
        $this->assertDatabaseCount('buzon_entries', 2);
        $this->assertDatabaseCount('reports', 0);
        $this->assertDatabaseCount('beneficiaries', 0);
        $this->assertDatabaseCount('indicador_proyecto', 0);
        $this->assertDatabaseCount('report_indicator_copies', 0);
        $this->assertDatabaseCount('system_notifications', 0);
    }

    public function test_anonymous_entry_is_allowed_and_identity_fields_cannot_spoof_the_registrant(): void
    {
        $this->post(route('buzon.store'), $this->payload(['created_by' => 99999, 'report_id' => 123]))->assertSessionHasNoErrors();
        $entry = BuzonEntry::sole();
        $this->assertNull($entry->full_name);
        $this->assertNull($entry->phone);
        $this->assertNull($entry->age);
        $this->assertNull($entry->afro_descendant);
        $this->assertSame($this->admin->id, $entry->created_by);
        $this->get(route('buzon.index'))->assertOk()->assertSee('Anónimo / Sin nombre')->assertSee('No representa personas únicas', false);
    }

    public function test_all_planilla_fields_are_saved_and_html_is_escaped_in_detail(): void
    {
        $this->post(route('buzon.store'), $this->payload(['full_name' => '  Persona de prueba  ', 'phone' => '04140000000',
            'age' => '0', 'gender' => 'non_binary_other', 'afro_descendant' => '0', 'disabilities' => ['visual', 'hearing'],
            'comment' => '<script>PRIVATE_TEXT</script>', 'improvement' => 'Mejorar comunicación']))->assertSessionHasNoErrors();
        $entry = BuzonEntry::sole();
        $this->assertSame('Persona de prueba', $entry->full_name);
        $this->assertSame(0, $entry->age);
        $this->assertFalse($entry->afro_descendant);
        $this->assertSame(['visual', 'hearing'], $entry->disabilities);
        $this->get(route('buzon.show', $entry))->assertOk()->assertSee('Mejorar comunicación')
            ->assertSee('&lt;script&gt;PRIVATE_TEXT&lt;/script&gt;', false)->assertDontSee('<script>PRIVATE_TEXT</script>', false)
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->get(route('buzon.edit', $entry))->assertOk()->assertSee('Guardar cambios');
    }

    public function test_authorized_users_only_access_their_projects_not_group_peers_or_countrywide_projects(): void
    {
        $own = $this->entry(['full_name' => 'OWN RECORD']);
        $foreign = $this->entry(['proyecto_id' => $this->second->id, 'full_name' => 'FOREIGN RECORD']);
        $user = User::factory()->create(['role' => 'coordinator', 'countrywide_access' => true]);
        $user->givePermissionTo('Buzon');
        $user->projects()->attach($this->first);
        $this->actingAs($user)->get(route('buzon.index'))->assertOk()->assertSee('OWN RECORD')->assertDontSee('FOREIGN RECORD')
            ->assertViewHas('entries', fn ($entries) => $entries->total() === 1);
        $this->get(route('buzon.show', $own))->assertOk();
        $this->get(route('buzon.show', $foreign))->assertNotFound();
        $this->get(route('buzon.edit', $foreign))->assertNotFound();
        $this->putJson(route('buzon.update', $foreign), $this->payload(['proyecto_id' => $this->second->id, 'version' => 1]))->assertForbidden();
        $this->get(route('buzon.create', ['proyecto_id' => $this->second->id]))->assertForbidden();
        $this->postJson(route('buzon.store'), $this->payload(['proyecto_id' => $this->second->id]))->assertUnprocessable()->assertJsonValidationErrors('proyecto_id');
        $this->getJson(route('buzon.index', ['project_ids' => [$this->first->id, $this->second->id]]))->assertUnprocessable();
        $this->putJson(route('buzon.update', $own), $this->payload(['proyecto_id' => $this->second->id, 'version' => 1]))->assertUnprocessable();
        $user->projects()->detach();
        $this->actingAs($user->fresh())->get(route('buzon.index'))->assertOk()->assertViewHas('entries', fn ($entries) => $entries->total() === 0);
        $this->get(route('buzon.show', $own))->assertNotFound();
    }

    public function test_combined_filters_dates_projects_opinion_and_feedback_match_summary(): void
    {
        $this->entry(['opinion_date' => '2026-01-01']);
        $matching = $this->entry(['proyecto_id' => $this->second->id, 'opinion_date' => '2026-01-15', 'opinion' => 'bad', 'feedback_type' => 'complaint']);
        $this->entry(['opinion_date' => '2026-01-16', 'opinion' => 'neutral', 'feedback_type' => 'comment']);
        $this->entry(['opinion_date' => '2026-02-01', 'opinion' => 'bad', 'feedback_type' => 'complaint']);
        $params = ['project_ids' => [$this->first->id, $this->second->id], 'from' => '2026-01-01', 'to' => '2026-01-31', 'opinion' => 'bad', 'feedback_type' => 'complaint'];
        $page = $this->get(route('buzon.index', $params))->assertOk();
        $this->assertSame([$matching->id], $page->viewData('entries')->modelKeys());
        $this->assertSame(1, (int) $page->viewData('counts')->sum());
        $this->assertSame(1, (int) $page->viewData('counts')['bad']);
        $this->get(route('buzon.index', ['project_ids' => [$this->first->id]]))->assertOk()->assertViewHas('entries', fn ($entries) => $entries->total() === 3);
        $this->get(route('buzon.index', ['from' => '2026-01-15', 'to' => '2026-01-15']))->assertOk()->assertViewHas('entries', fn ($entries) => $entries->total() === 1);
        $this->get(route('buzon.index', ['to' => '2026-01-01']))->assertOk()->assertViewHas('entries', fn ($entries) => $entries->total() === 1);
        $this->getJson(route('buzon.index', ['from' => '2026-02-01', 'to' => '2026-01-01']))->assertUnprocessable();
        $this->get(route('buzon.index', ['project_ids' => '']))->assertOk()->assertViewHas('entries', fn ($entries) => $entries->total() === 4);
    }

    public function test_invalid_fields_and_incompatible_disabilities_do_not_create_data(): void
    {
        foreach ([['opinion' => 'unknown'], ['feedback_type' => 'unknown'], ['age' => 121], ['age' => -1], ['age' => 2.5],
            ['gender' => 'unknown'], ['afro_descendant' => 'unknown'], ['disabilities' => ['none', 'visual']],
            ['disabilities' => ['visual', 'visual']], ['disabilities' => ['unknown']], ['opinion_date' => now()->addDay()->toDateString()],
            ['place_name' => '   '], ['comment' => str_repeat('a', 10001)]] as $extra) {
            $this->postJson(route('buzon.store'), $this->payload($extra))->assertUnprocessable();
        }
        $this->assertDatabaseCount('buzon_entries', 0);
        $this->assertDatabaseCount('reports', 0);
        $this->assertDatabaseCount('beneficiaries', 0);
    }

    public function test_edit_preserves_creator_clears_disabilities_and_prevents_lost_updates(): void
    {
        $entry = $this->entry(['full_name' => 'Original', 'disabilities' => ['visual']]);
        $this->put(route('buzon.update', $entry), $this->payload(['version' => '1', 'full_name' => 'Corregido', 'created_by' => 99999]))->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('Corregido', $entry->fresh()->full_name);
        $this->assertSame([], $entry->fresh()->disabilities);
        $this->assertSame(2, $entry->fresh()->version);
        $this->assertSame($this->admin->id, $entry->fresh()->created_by);
        $this->putJson(route('buzon.update', $entry), $this->payload(['version' => 1, 'full_name' => 'Stale']))->assertUnprocessable()->assertJsonValidationErrors('version');
        $this->assertSame('Corregido', $entry->fresh()->full_name);
        $this->assertDatabaseCount('buzon_entries', 1);
        $this->assertDatabaseCount('reports', 0);
    }

    public function test_private_payload_never_appears_in_general_audit_even_on_invalid_requests(): void
    {
        $this->post(route('buzon.store'), $this->payload(['full_name' => 'PRIVATE_NAME', 'phone' => 'PRIVATE_PHONE', 'comment' => 'PRIVATE_COMMENT']))->assertSessionHasNoErrors();
        $this->postJson(route('buzon.store'), $this->payload(['opinion' => 'invalid', 'comment' => 'PRIVATE_INVALID']))->assertUnprocessable();
        $this->get(route('buzon.index', ['opinion' => 'bad']))->assertOk();
        $logs = AuditLog::where('route_name', 'like', 'buzon.%')->get();
        $this->assertCount(3, $logs);
        foreach ($logs as $log) $this->assertNull($log->request_payload);
        $this->assertStringNotContainsString('PRIVATE_', $logs->toJson());
    }

    public function test_inactive_projects_remain_consultable_and_existing_entries_can_be_corrected(): void
    {
        $entry = $this->entry();
        $this->first->update(['estatus' => false]);
        $this->get(route('buzon.index'))->assertOk()->assertViewHas('entries', fn ($entries) => $entries->total() === 1);
        $this->get(route('buzon.show', $entry))->assertOk();
        $this->put(route('buzon.update', $entry), $this->payload(['version' => 1]))->assertSessionHasNoErrors();
        $this->postJson(route('buzon.store'), $this->payload())->assertUnprocessable();
        $this->delete(route('proyectos.destroy', $this->first))->assertSessionHas('error');
        $this->assertDatabaseHas('proyectos', ['id' => $this->first->id]);
        $this->assertDatabaseCount('buzon_entries', 1);
    }

    public function test_read_only_detail_does_not_show_stale_old_input_and_form_survives_invalid_arrays(): void
    {
        $entry = $this->entry(['comment' => 'SAVED_COMMENT']);
        $this->withSession(['_old_input' => ['comment' => 'UNSAVED_COMMENT', 'disabilities' => 'invalid', 'place_name' => ['invalid']]])
            ->get(route('buzon.show', $entry))->assertOk()->assertSee('SAVED_COMMENT')->assertDontSee('UNSAVED_COMMENT');
        $this->get(route('buzon.edit', $entry))->assertOk();
    }
}
