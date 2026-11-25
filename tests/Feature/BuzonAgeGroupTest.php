<?php

namespace Tests\Feature;

use App\Models\{BuzonEntry, Donante, Proyecto, User, UserGroup};
use App\Support\PublicBuzonLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BuzonAgeGroupTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;
    private Proyecto $project;

    protected function setUp(): void
    {
        parent::setUp();
        $donor = Donante::create(['nombre' => 'Donante de prueba', 'estatus' => true]);
        $this->project = Proyecto::create(['donante_id' => $donor->id, 'codigo' => 'AGE-A', 'descripcion' => 'Proyecto A', 'estatus' => true]);
        $this->actor = User::factory()->create(['role' => 'reporter']);
        $this->actor->syncRoles([]);
        $this->actor->givePermissionTo(['ver buzon', 'crear buzon', 'editar buzon', 'exportar buzon']);
        $this->actor->projects()->attach($this->project);
        $this->actingAs($this->actor);
    }

    private function payload(array $extra = []): array
    {
        return array_replace(['proyecto_id' => $this->project->id, 'opinion_date' => today()->toDateString(),
            'place_name' => 'Comunidad de prueba', 'opinion' => 'very_good', 'feedback_type' => 'comment', 'comment' => 'Opinión de prueba'], $extra);
    }

    private function entry(array $extra = [], ?User $user = null): BuzonEntry
    {
        return BuzonEntry::create($this->payload($extra) + ['created_by' => ($user ?? $this->actor)->id]);
    }

    private function table(array $extra = [], string $route = 'buzon.index')
    {
        return $this->getJson(route($route, array_replace(['draw' => 1, 'length' => 20, 'start' => 0], $extra)));
    }

    private function xpath(string $html): \DOMXPath
    {
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        return new \DOMXPath($dom);
    }

    public function test_internal_and_public_forms_offer_optional_radios_and_editing_preserves_the_selection(): void
    {
        $url = PublicBuzonLink::url($this->project, $this->actor);
        $internal = $this->get(route('buzon.create'))->assertOk();
        auth()->logout();
        $public = $this->get($url)->assertOk();
        foreach ([$internal, $public] as $page) {
            $xpath = $this->xpath($page->getContent());
            $this->assertSame(3, $xpath->query('//input[@type="radio" and @name="age_group"]')->length);
            $this->assertSame(0, $xpath->query('//input[@name="age_group" and @required]')->length);
            $this->assertSame(1, $xpath->query('//input[@name="age_group" and @value="" and @checked]')->length);
        }
        $entry = $this->entry(['age_group' => 'nna']);
        $this->actingAs($this->actor);
        foreach (['buzon.edit', 'buzon.show'] as $route) {
            $xpath = $this->xpath($this->get(route($route, $entry))->assertOk()->getContent());
            $this->assertSame(1, $xpath->query('//input[@name="age_group" and @value="nna" and @checked]')->length);
        }
    }

    public function test_new_opinions_save_the_selected_group_with_or_without_an_exact_age_and_reset_the_form(): void
    {
        foreach ([['nna', 0], ['nna', 17], ['adult', 18], ['adult', 120], ['nna', null], ['adult', null], [null, null]] as [$group, $age]) {
            $response = $this->post(route('buzon.store'), $this->payload(['age_group' => $group, 'age' => $age]))->assertRedirect();
            $this->assertSame($group, BuzonEntry::latest('id')->firstOrFail()->age_group);
            $xpath = $this->xpath($this->get($response->headers->get('Location'))->assertOk()->getContent());
            $this->assertSame(1, $xpath->query('//form[@data-buzon-entry]//input[@name="age_group" and @value="" and @checked]')->length);
        }
        $this->assertDatabaseCount('reports', 0);
        $this->assertDatabaseCount('beneficiaries', 0);
    }

    public function test_invalid_groups_and_age_conflicts_are_rejected_on_create_and_update(): void
    {
        foreach (['invalid', 'unspecified', ['nna']] as $group) {
            $this->postJson(route('buzon.store'), $this->payload(['age_group' => $group]))->assertUnprocessable()->assertJsonValidationErrors('age_group');
        }
        foreach ([['nna', 18], ['adult', 17]] as [$group, $age]) {
            $this->postJson(route('buzon.store'), $this->payload(['age_group' => $group, 'age' => $age]))->assertUnprocessable()->assertJsonValidationErrors('age_group');
        }
        $this->assertDatabaseCount('buzon_entries', 0);
        $entry = $this->entry(['age_group' => 'nna', 'age' => 17]);
        $this->putJson(route('buzon.update', $entry), $this->payload(['version' => 1, 'age_group' => 'adult']))
            ->assertUnprocessable()->assertJsonValidationErrors('age_group');
        $this->put(route('buzon.update', $entry), $this->payload(['version' => 1, 'age_group' => 'adult', 'age' => 18]))->assertRedirect();
        $this->assertSame('adult', $entry->fresh()->age_group);
        $this->put(route('buzon.update', $entry), $this->payload(['version' => 2]))->assertRedirect();
        $this->assertSame('adult', $entry->fresh()->age_group, 'An omitted group must not erase a saved selection.');
        $this->put(route('buzon.update', $entry), $this->payload(['version' => 3, 'age_group' => null]))->assertRedirect();
        $this->assertNull($entry->fresh()->age_group);
    }

    public function test_public_submissions_validate_and_store_the_group_without_changing_deduplication(): void
    {
        $url = PublicBuzonLink::url($this->project, $this->actor);
        auth()->logout();
        $payload = $this->payload(['age_group' => 'adult', 'age' => 17, 'submission_token' => Str::random(64)]);
        unset($payload['proyecto_id']);
        $this->postJson($url, $payload)->assertUnprocessable()->assertJsonValidationErrors('age_group');
        $payload['age'] = 18;
        $this->post($url, $payload)->assertStatus(303);
        $this->post($url, $payload)->assertStatus(303);
        $this->assertDatabaseCount('buzon_entries', 1);
        $entry = BuzonEntry::sole();
        $this->assertSame('adult', $entry->age_group);
        $this->assertTrue($entry->submitted_via_public_link);
        $this->assertNull($entry->created_by);
        $this->assertSame($this->actor->id, $entry->public_recipient_id);
    }

    public function test_consultation_search_counts_and_exports_apply_the_same_age_group_filter(): void
    {
        $nna = $this->entry(['age_group' => 'nna']);
        $this->entry(['age_group' => 'adult']);
        $legacy = $this->entry(['age' => 12]);
        $this->table()->assertOk()->assertJsonPath('recordsTotal', 3);
        foreach (['nna', 'adult', 'unspecified'] as $group) {
            $this->get(route('buzon.index', ['age_group' => $group]))->assertOk()
                ->assertViewHas('entries', fn ($rows) => $rows->total() === 1)
                ->assertViewHas('counts', fn ($counts) => $counts->sum() === 1);
            $this->table(['age_group' => $group])->assertOk()->assertJsonPath('recordsTotal', 1)->assertJsonCount(1, 'data');
            foreach (['excel', 'pdf'] as $format) {
                $this->table(['age_group' => $group, 'export_type' => $format])->assertOk()
                    ->assertJsonCount(1, 'data')->assertJsonPath('data.0.age_group', BuzonEntry::AGE_GROUPS[$group] ?? 'No indicado');
            }
        }
        $this->table(['search' => ['value' => 'NNA']])->assertOk()->assertJsonPath('recordsFiltered', 1);
        $this->table(['search' => ['value' => 'Adultos']])->assertOk()->assertJsonPath('recordsFiltered', 1);
        $this->table(['search' => ['value' => 'No indicado']])->assertOk()->assertJsonPath('recordsFiltered', 1);
        $this->table(['age_group' => 'nna', 'search' => ['value' => 'Adultos']])->assertOk()->assertJsonCount(0, 'data');
        $this->table(['age_group' => 'nna', 'order' => [['column' => 8, 'dir' => 'asc']]])->assertOk();
        $this->assertNull($legacy->fresh()->age_group, 'Historical ages must not silently assign the new group.');
        $this->assertSame('nna', $nna->fresh()->age_group);
        foreach (['invalid', ['nna']] as $group) $this->table(['age_group' => $group])->assertUnprocessable();
    }

    public function test_age_group_filter_never_bypasses_projects_groups_permissions_or_public_only_scope(): void
    {
        $peer = User::factory()->create(['role' => 'reporter']);
        $peer->projects()->attach($this->project);
        $group = UserGroup::create(['name' => 'Grupo de prueba', 'is_active' => true]);
        $group->users()->attach([$this->actor->id, $peer->id]);
        $this->entry(['age_group' => 'nna']);
        $this->entry(['age_group' => 'nna'], $peer);
        $outsider = User::factory()->create(['role' => 'reporter']);
        $this->entry(['age_group' => 'nna', 'full_name' => 'PRIVATE_OTHER_TEAM'], $outsider);
        $other = Proyecto::create(['donante_id' => $this->project->donante_id, 'codigo' => 'AGE-B', 'descripcion' => 'Proyecto B', 'estatus' => true]);
        $this->entry(['proyecto_id' => $other->id, 'age_group' => 'nna', 'full_name' => 'PRIVATE_PROJECT']);
        $external = $this->entry(['age_group' => 'nna']);
        $external->forceFill(['created_by' => null, 'submitted_via_public_link' => true, 'public_recipient_id' => $this->actor->id])->save();
        $this->table(['age_group' => 'nna'])->assertOk()->assertJsonPath('recordsTotal', 3)
            ->assertDontSee('PRIVATE_OTHER_TEAM')->assertDontSee('PRIVATE_PROJECT');
        $this->table(['age_group' => 'nna'], 'buzon.public.index')->assertOk()->assertJsonPath('recordsTotal', 1);
        $this->table(['age_group' => 'nna', 'project_ids' => [$other->id]])->assertUnprocessable();
        $group->update(['is_active' => false]);
        $this->table(['age_group' => 'nna'])->assertOk()->assertJsonPath('recordsTotal', 2);
        $this->actor->revokePermissionTo('exportar buzon');
        $this->table(['age_group' => 'nna', 'export_type' => 'excel'])->assertForbidden();
        $this->actor->revokePermissionTo('ver buzon');
        $this->actingAs($this->actor->fresh())->table(['age_group' => 'nna'])->assertForbidden();
    }

    public function test_migration_keeps_historical_ages_and_does_not_classify_previous_opinions(): void
    {
        $legacy = $this->entry(['age' => 12]);
        Schema::table('buzon_entries', function ($table): void {
            $table->dropIndex(['age_group']);
            $table->dropColumn('age_group');
        });
        $migration = require database_path('migrations/2026_11_25_120000_add_age_group_to_buzon_entries.php');
        $migration->up();
        $this->assertNull($legacy->fresh()->age_group);
        $this->assertSame(12, $legacy->fresh()->age);
        $this->assertDatabaseCount('buzon_entries', 1);
    }
}
