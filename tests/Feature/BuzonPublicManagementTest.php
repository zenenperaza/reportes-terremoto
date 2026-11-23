<?php

namespace Tests\Feature;

use App\Models\{BuzonEntry, Donante, Proyecto, User, UserGroup};
use App\Support\PublicBuzonLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuzonPublicManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;
    private User $peer;
    private Proyecto $project;
    private UserGroup $group;

    protected function setUp(): void
    {
        parent::setUp();
        $donor = Donante::create(['nombre' => 'Donante', 'estatus' => true]);
        $this->project = Proyecto::create(['donante_id' => $donor->id, 'codigo' => 'PUBLIC-A', 'descripcion' => 'Proyecto A', 'estatus' => true]);
        $other = Proyecto::create(['donante_id' => $donor->id, 'codigo' => 'PRIVATE-B', 'descripcion' => 'Proyecto B', 'estatus' => true]);
        $this->actor = User::factory()->create(['role' => 'reporter']);
        $this->actor->syncRoles([]);
        $this->actor->givePermissionTo(['ver buzon', 'crear buzon', 'exportar buzon']);
        $this->peer = User::factory()->create(['role' => 'reporter']);
        $outsider = User::factory()->create(['role' => 'reporter']);
        foreach ([$this->actor, $this->peer, $outsider] as $user) $user->projects()->attach($this->project);
        $this->group = UserGroup::create(['name' => 'Equipo', 'is_active' => true, 'allow_member_editing' => false]);
        $this->group->users()->attach([$this->actor->id, $this->peer->id]);
        $this->entry($this->actor, 'INTERNA_PROPIA', false);
        $this->entry($this->actor, 'EXTERNA_PROPIA', true);
        $this->entry($this->peer, 'EXTERNA_EQUIPO', true);
        $this->entry($outsider, 'EXTERNA_OTRO_EQUIPO', true);
        $this->entry($this->peer, 'EXTERNA_OTRO_PROYECTO', true, $other);
        $this->actingAs($this->actor);
    }

    private function entry(User $owner, string $name, bool $public, ?Proyecto $project = null): BuzonEntry
    {
        $entry = new BuzonEntry(['proyecto_id' => ($project ?? $this->project)->id, 'opinion_date' => '2026-11-20',
            'full_name' => $name, 'place_name' => 'Comunidad', 'opinion' => 'very_good', 'feedback_type' => 'comment']);
        $entry->forceFill(['created_by' => $public ? null : $owner->id, 'submitted_via_public_link' => $public, 'public_recipient_id' => $public ? $owner->id : null])->save();
        return $entry;
    }

    public function test_share_panel_moves_to_its_own_page_and_menu_without_changing_signed_links(): void
    {
        $link = PublicBuzonLink::url($this->project, $this->actor);
        $this->get(route('buzon.index'))->assertOk()->assertSee('sidebarBuzon')
            ->assertSee(route('buzon.public.index'), false)->assertDontSee('data-buzon-public-link', false)
            ->assertDontSee('js/buzon-public-link.js', false)->assertSee('INTERNA_PROPIA');
        $this->get(route('buzon.public.index'))->assertOk()->assertSee('data-buzon-public-link', false)
            ->assertSee('Descargar QR (PNG)')->assertSee('data-public-url="'.e($link).'"', false)
            ->assertSee('js/buzon-public-link.js', false)->assertSee('vendor/qrcodegen/qrcodegen.js', false)
            ->assertSee('id="buzon-opinions"', false)->assertSee('data-url="'.route('buzon.public.index').'"', false)
            ->assertDontSee('PRIVATE-B');
        $this->assertSame($link, PublicBuzonLink::url($this->project, $this->actor));
    }

    public function test_public_html_counts_and_pagination_only_include_visible_external_entries(): void
    {
        $this->get(route('buzon.public.index'))->assertOk()->assertViewHas('counts', fn ($counts) => (int) $counts->sum() === 2)
            ->assertViewHas('entries', fn ($entries) => $entries->total() === 2)
            ->assertSee('EXTERNA_PROPIA')->assertSee('EXTERNA_EQUIPO')->assertDontSee('INTERNA_PROPIA')
            ->assertDontSee('EXTERNA_OTRO_EQUIPO')->assertDontSee('EXTERNA_OTRO_PROYECTO');
        $this->group->update(['is_active' => false]);
        $this->get(route('buzon.public.index'))->assertOk()->assertViewHas('entries', fn ($entries) => $entries->total() === 1)->assertDontSee('EXTERNA_EQUIPO');
    }

    public function test_search_and_both_exports_cannot_override_the_public_only_scope(): void
    {
        foreach ([null, 'excel', 'pdf'] as $format) {
            $query = ['draw' => 1, 'length' => 1, 'project_ids' => [$this->project->id],
                'submitted_via_public_link' => '0', 'publicOnly' => '0', 'from' => '2026-11-20', 'to' => '2026-11-20'];
            if ($format) $query['export_type'] = $format;
            $this->getJson(route('buzon.public.index', $query))->assertOk()->assertHeader('Cache-Control', 'no-store, private')
                ->assertJsonPath('recordsTotal', 2)->assertJsonPath('recordsFiltered', 2)->assertJsonCount($format ? 2 : 1, 'data')
                ->assertDontSee('INTERNA_PROPIA')->assertDontSee('EXTERNA_OTRO');
            $this->getJson(route('buzon.public.index', $query + ['search' => ['value' => 'INTERNA']]))->assertOk()->assertJsonCount(0, 'data');
        }
        $this->actor->revokePermissionTo('exportar buzon');
        $this->getJson(route('buzon.public.index', ['draw' => 1, 'export_type' => 'excel']))->assertForbidden();
    }

    public function test_create_only_can_generate_links_but_never_read_entries_counts_or_exports(): void
    {
        $this->actor->revokePermissionTo('ver buzon');
        $this->get(route('buzon.public.index'))->assertOk()->assertSee('data-buzon-public-link', false)
            ->assertDontSee('id="buzon-table"', false)->assertDontSee('Formularios recibidos')
            ->assertDontSee('EXTERNA_PROPIA')->assertDontSee('INTERNA_PROPIA')->assertDontSee('href="'.route('buzon.index').'"', false);
        $this->getJson(route('buzon.public.index', ['draw' => 1]))->assertForbidden();
        $this->getJson(route('buzon.public.index', ['draw' => 1, 'export_type' => 'pdf']))->assertForbidden();
        $this->get(route('buzon.index'))->assertForbidden();
        $this->actor->revokePermissionTo('crear buzon');
        $this->get(route('buzon.public.index'))->assertForbidden();
        $this->get(route('dashboard'))->assertOk()->assertDontSee('id="sidebarBuzon"', false);
    }

    public function test_reader_can_consult_public_entries_without_receiving_links_or_qr_controls(): void
    {
        $this->actor->revokePermissionTo('crear buzon');
        $this->get(route('buzon.public.index'))->assertOk()->assertSee('EXTERNA_PROPIA')->assertDontSee('data-buzon-public-link', false)
            ->assertDontSee('data-public-url=', false)->assertDontSee('js/buzon-public-link.js', false);
        $this->getJson(route('buzon.public.index', ['draw' => 1]))->assertOk()->assertJsonPath('recordsFiltered', 2);
        $this->get(route('buzon.index'))->assertOk()->assertViewHas('entries', fn ($entries) => $entries->total() === 3);
    }
}
