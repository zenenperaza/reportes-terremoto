<?php

namespace Tests\Feature;

use App\Models\{AuditLog, BuzonEntry, Donante, Proyecto, User, UserGroup};
use App\Support\PublicBuzonLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{RateLimiter, URL};
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PublicBuzonTest extends TestCase
{
    use RefreshDatabase;

    private Proyecto $project;
    private Proyecto $other;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $donor = Donante::create(['nombre' => 'Donante de prueba', 'estatus' => true]);
        $this->project = Proyecto::create(['donante_id' => $donor->id, 'codigo' => 'VNZ/PCA202380/PD2023237',
            'nombre_alias' => 'Proyecto público de prueba', 'descripcion' => 'DESCRIPCION_INTERNA_NO_PUBLICA', 'estatus' => true]);
        $this->other = Proyecto::create(['donante_id' => $donor->id, 'codigo' => 'OTHER-PRIVATE', 'descripcion' => 'Otro', 'estatus' => true]);
        $this->owner = User::factory()->create(['role' => 'reporter', 'name' => 'RESPONSABLE_INTERNO', 'is_active' => true]);
        $this->owner->givePermissionTo(['crear buzon', 'ver buzon', 'editar buzon', 'eliminar buzon']);
        $this->owner->projects()->attach($this->project);
    }

    private function payload(array $extra = []): array
    {
        return array_replace(['opinion_date' => today()->toDateString(), 'place_name' => 'Comunidad de prueba',
            'opinion' => 'neutral', 'feedback_type' => 'suggestion', 'comment' => 'SUGERENCIA_PUBLICA_PRIVADA'], $extra);
    }

    private function url(?Proyecto $project = null, ?User $owner = null): string
    {
        return PublicBuzonLink::url($project ?? $this->project, $owner ?? $this->owner);
    }

    public function test_index_exposes_only_assigned_active_projects_and_only_with_create_permission(): void
    {
        $this->actingAs($this->owner)->get(route('buzon.index'))->assertOk()->assertSee('Enlace público del Buzón')
            ->assertSee('data-public-url="'.e($this->url()).'"', false)->assertDontSee('OTHER-PRIVATE')
            ->assertSee('Descargar QR (PNG)')->assertSee('data-public-qr-canvas', false)
            ->assertSee('vendor/qrcodegen/qrcodegen.js')->assertSee('data-project-code="'.e($this->project->codigo).'"', false);
        $this->project->update(['estatus' => false]);
        $this->get(route('buzon.index'))->assertOk()->assertSee('No tiene proyectos activos')
            ->assertDontSee('data-public-url=', false);
        $this->owner->revokePermissionTo('crear buzon');
        $this->get(route('buzon.index'))->assertOk()->assertDontSee('data-buzon-public-link', false);
    }

    public function test_share_panel_keeps_project_link_and_qr_in_separate_compact_containers(): void
    {
        $page = $this->actingAs($this->owner)->get(route('buzon.index'))->assertOk();
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$page->getContent());
        $xpath = new \DOMXPath($dom);
        $section = $xpath->query('//section[@data-buzon-public-link]')->item(0);
        $this->assertNotNull($section);
        $this->assertSame(1, $xpath->query('.//div[contains(@class,"buzon-share-controls")]//select[@data-public-project]', $section)->length);
        $this->assertSame(1, $xpath->query('.//label[contains(@class,"buzon-share-field")]/select[@data-public-project]', $section)->length);
        $this->assertSame(0, $xpath->query('.//*[contains(@class,"form-grid")]', $section)->length);
        $this->assertSame(1, $xpath->query('.//aside[contains(@class,"buzon-share-preview")]//figure[@data-public-qr-panel]', $section)->length);
        $this->assertSame(0, $xpath->query('.//*[@data-public-link-panel]//figure', $section)->length);
        $this->assertSame(1, $xpath->query('.//*[@data-public-qr-empty]', $section)->length);
        $this->assertSame(1, $xpath->query('.//*[@data-public-link-copy][@type="button"]', $section)->length);
    }

    public function test_public_page_needs_no_login_discloses_only_selected_project_and_has_private_security_headers(): void
    {
        BuzonEntry::create(['proyecto_id' => $this->project->id, 'created_by' => $this->owner->id] + $this->payload(['full_name' => 'NOMBRE_INTERNO_NO_PUBLICO']));
        $this->withSession(['_old_input' => ['full_name' => 'DATOS_ANTIGUOS_OTRO_FORMULARIO']]);
        $page = $this->get($this->url())->assertOk()->assertSee($this->project->codigo)->assertSee('Enviar opinión')
            ->assertDontSee('NOMBRE_INTERNO_NO_PUBLICO')->assertDontSee('SUGERENCIA_PUBLICA_PRIVADA')
            ->assertDontSee('RESPONSABLE_INTERNO')->assertDontSee('OTHER-PRIVATE')
            ->assertDontSee('DESCRIPCION_INTERNA_NO_PUBLICA')->assertDontSee('DATOS_ANTIGUOS_OTRO_FORMULARIO')
            ->assertDontSee('Consultar listados')->assertDontSee('name="proyecto_id"', false)
            ->assertSee('name="_token"', false)->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('X-Frame-Options', 'DENY')->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
        $this->assertTrue($page->headers->hasCacheControlDirective('no-store'));
        $this->assertStringContainsString("frame-ancestors 'none'", $page->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString("form-action 'self' https://asonacop.org;", $page->headers->get('Content-Security-Policy'));
        $this->assertGuest();
    }

    public function test_anonymous_post_saves_opinion_then_redirects_to_fixed_asonacop_home_without_personal_data(): void
    {
        $auditCount = AuditLog::count();
        $this->post($this->url(), $this->payload(['full_name' => 'NOMBRE_EXTERNO_PRUEBA', 'phone' => '0000000000',
            'redirect_to' => 'https://example.test/untrusted']))
            ->assertStatus(303)->assertRedirect('https://asonacop.org/')->assertSessionHasNoErrors()
            ->assertSessionMissing('public_buzon_success')->assertHeader('Referrer-Policy', 'no-referrer');
        $entry = BuzonEntry::sole();
        $this->assertNull($entry->created_by);
        $this->assertNull($entry->buzon_list_id);
        $this->assertSame($this->owner->id, $entry->public_recipient_id);
        $this->assertSame($this->project->id, $entry->proyecto_id);
        $this->assertTrue($entry->submitted_via_public_link);
        $this->assertSame(1, $entry->version);
        $this->assertSame('NOMBRE_EXTERNO_PRUEBA', $entry->full_name);
        $this->get($this->url())->assertOk()->assertDontSee('NOMBRE_EXTERNO_PRUEBA')->assertDontSee('SUGERENCIA_PUBLICA_PRIVADA');
        $this->assertDatabaseCount('buzon_entries', 1);
        $this->assertDatabaseCount('buzon_lists', 0);
        $this->assertDatabaseCount('reports', 0);
        $this->assertDatabaseCount('beneficiaries', 0);
        $this->assertSame($auditCount, AuditLog::count());
        $this->assertGuest();
    }

    public function test_logged_in_staff_using_public_form_remains_anonymous_and_never_leaks_payload_to_audit_log(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->post($this->url(), $this->payload())->assertRedirect();
        $this->assertNull(BuzonEntry::sole()->created_by);
        $this->assertDatabaseMissing('audit_logs', ['route_name' => 'public-buzon.store']);
        $this->assertAuthenticatedAs($admin);
    }

    public function test_index_distinguishes_participant_staff_author_and_external_link_recipient(): void
    {
        $this->post($this->url(), $this->payload(['full_name' => 'PARTICIPANTE_EXTERNO', 'place_name' => 'LUGAR_EXTERNO']))->assertRedirect();
        BuzonEntry::create(['proyecto_id' => $this->project->id, 'created_by' => $this->owner->id]
            + $this->payload(['full_name' => 'PARTICIPANTE_INTERNO', 'place_name' => 'LUGAR_INTERNO']));
        $page = $this->actingAs($this->owner)->get(route('buzon.index'))->assertOk()
            ->assertSee('<th>Usuario</th><th>Origen</th>', false);
        foreach ($page->viewData('entries') as $entry) {
            $this->assertTrue($entry->relationLoaded('creator'));
            $this->assertTrue($entry->relationLoaded('publicRecipient'));
        }
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$page->getContent());
        $xpath = new \DOMXPath($dom);
        $this->assertSame(9, $xpath->query('//table[contains(@class,"buzon-table")]/thead/tr/th')->length);
        foreach (['INTERNO', 'EXTERNO'] as $source) {
            $row = $xpath->query('//table[contains(@class,"buzon-table")]/tbody/tr[td[3]="LUGAR_'.$source.'"]')->item(0);
            $this->assertNotNull($row);
            $cells = $xpath->query('./td', $row);
            $this->assertSame(9, $cells->length);
            $this->assertSame('PARTICIPANTE_'.$source, trim($cells->item(5)->textContent));
            $this->assertStringContainsString('RESPONSABLE_INTERNO', $cells->item(6)->textContent);
            $this->assertSame($source === 'EXTERNO' ? 'Enlace externo' : 'Interno', trim($cells->item(7)->textContent));
            if ($source === 'EXTERNO') {
                $this->assertStringContainsString('Externo (sin usuario)', $cells->item(6)->textContent);
                $this->assertStringContainsString('Responsable del enlace:', $cells->item(6)->textContent);
            } else {
                $this->assertStringNotContainsString('Externo', $cells->item(6)->textContent);
            }
        }
        $this->owner->delete();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('buzon.index'))->assertOk()->assertSee('RESPONSABLE_INTERNO');
        BuzonEntry::create(['proyecto_id' => $this->project->id, 'created_by' => null]
            + $this->payload(['place_name' => 'SIN_AUTOR_HISTORICO']));
        $this->get(route('buzon.index'))->assertOk()->assertSee('Usuario no disponible')->assertSee('SIN_AUTOR_HISTORICO');
    }

    public function test_missing_and_tampered_signatures_or_project_codes_do_not_allow_reading_or_submitting(): void
    {
        $url = $this->url();
        $unsigned = strtok($url, '?');
        foreach ([$unsigned, str_replace('vnz-pca202380-pd2023237', 'fake-project', $url),
            str_replace('/'.$this->project->id.'/', '/'.$this->other->id.'/', $url), $url.'&unexpected=1'] as $invalid) {
            $this->getJson($invalid)->assertForbidden();
            $this->postJson($invalid, $this->payload())->assertForbidden();
        }
        $wrongCode = URL::signedRoute('public-buzon.show', ['proyecto' => $this->project->id, 'codigo' => 'wrong', 'recipient' => $this->owner->id]);
        $this->getJson($wrongCode)->assertNotFound();
        $this->assertDatabaseCount('buzon_entries', 0);
    }

    public static function disabledLinks(): array
    {
        return [['project'], ['owner'], ['permission'], ['assignment'], ['deleted-owner'], ['changed-code']];
    }

    #[DataProvider('disabledLinks')]
    public function test_link_is_disabled_when_owner_or_project_access_is_revoked(string $reason): void
    {
        $url = $this->url();
        match ($reason) {
            'project' => $this->project->update(['estatus' => false]),
            'owner' => $this->owner->update(['is_active' => false]),
            'permission' => $this->owner->revokePermissionTo('crear buzon'),
            'assignment' => $this->owner->projects()->detach($this->project),
            'deleted-owner' => $this->owner->delete(),
            'changed-code' => $this->project->update(['codigo' => 'NEW-CODE']),
        };
        $this->getJson($url)->assertNotFound();
        $this->postJson($url, $this->payload())->assertNotFound();
        $this->assertDatabaseCount('buzon_entries', 0);
    }

    public function test_validation_rejects_spoofing_honeypot_future_dates_arrays_and_empty_messages(): void
    {
        foreach ([['proyecto_id' => $this->other->id], ['created_by' => $this->owner->id], ['public_recipient_id' => $this->owner->id],
            ['submitted_via_public_link' => true], ['version' => 99], ['buzon_list_id' => 99], ['website' => 'spam'],
            ['opinion_date' => today()->addDay()->toDateString()], ['full_name' => ['bad']], ['comment' => '', 'improvement' => ''],
        ] as $invalid) {
            $this->postJson($this->url(), $this->payload($invalid))->assertUnprocessable();
            // Validation tests cover independent visitors rather than exhausting the IP rate limit.
            RateLimiter::clear('buzon-minute:'.hash('sha256', '127.0.0.1'));
            RateLimiter::clear('buzon-hour:'.hash('sha256', '127.0.0.1'));
        }
        $this->assertDatabaseCount('buzon_entries', 0);
    }

    public function test_validation_redirect_stays_on_signed_public_form_and_escapes_untrusted_text(): void
    {
        $this->from('/ingresar')->post($this->url(), $this->payload(['place_name' => '', 'full_name' => '<script>alert(1)</script>']))
            ->assertRedirect($this->url())->assertSessionHasErrors('place_name', null, 'publicBuzon');
        $this->get($this->url())->assertOk()->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        $this->post($this->url(), $this->payload(['full_name' => null, 'phone' => null, 'comment' => null, 'improvement' => 'MEJORA_ANONIMA']))->assertRedirect();
        $this->assertNull(BuzonEntry::sole()->full_name);
        $this->assertSame('MEJORA_ANONIMA', BuzonEntry::sole()->improvement);
    }

    public function test_public_entries_follow_recipient_project_group_and_cross_editing_permissions(): void
    {
        $this->post($this->url(), $this->payload())->assertRedirect();
        $entry = BuzonEntry::sole();
        $peer = User::factory()->create(['role' => 'reporter']);
        $outsider = User::factory()->create(['role' => 'reporter']);
        foreach ([$peer, $outsider] as $user) {
            $user->givePermissionTo(['ver buzon', 'editar buzon', 'eliminar buzon']);
            $user->projects()->attach($this->project);
        }
        $group = UserGroup::create(['name' => 'Equipo receptor', 'is_active' => true, 'allow_member_editing' => false]);
        $group->users()->attach([$this->owner->id, $peer->id]);
        $this->actingAs($this->owner)->get(route('buzon.index'))->assertOk()->assertSee('Enlace público');
        $this->get(route('buzon.show', $entry))->assertOk()->assertSee('SUGERENCIA_PUBLICA_PRIVADA')->assertSee('Responsable del enlace');
        $this->actingAs($outsider)->getJson(route('buzon.show', $entry))->assertNotFound();
        $this->actingAs($peer)->get(route('buzon.show', $entry))->assertOk();
        $this->getJson(route('buzon.edit', $entry))->assertForbidden();
        $group->update(['allow_member_editing' => true]);
        $this->get(route('buzon.edit', $entry))->assertOk();
        $this->put(route('buzon.update', $entry), $this->payload(['proyecto_id' => $this->project->id, 'version' => 1, 'comment' => 'CORREGIDA']))->assertRedirect();
        $this->assertSame('CORREGIDA', $entry->fresh()->comment);
        $group->update(['is_active' => false]);
        $this->getJson(route('buzon.show', $entry))->assertNotFound();
        $group->update(['is_active' => true]);
        $peer->projects()->detach($this->project);
        $this->getJson(route('buzon.show', $entry))->assertNotFound();
        $this->actingAs($this->owner)->delete(route('buzon.destroy', $entry), ['version' => 2])->assertRedirect();
        $this->assertDatabaseCount('buzon_entries', 0);
    }

    public function test_maintenance_lock_blocks_public_posts_and_ip_limit_prevents_unbounded_submissions(): void
    {
        \App\Models\SystemSetting::create(['key' => \App\Models\SystemSetting::MAINTENANCE_MODE, 'value' => '1']);
        $this->getJson($this->url())->assertServiceUnavailable();
        $this->postJson($this->url(), $this->payload())->assertServiceUnavailable();
        \App\Models\SystemSetting::where('key', \App\Models\SystemSetting::MAINTENANCE_MODE)->update(['value' => '0']);
        $this->travel(61)->seconds();
        for ($i = 0; $i < 10; $i++) $this->post($this->url(), $this->payload())->assertRedirect();
        $this->postJson($this->url(), $this->payload())->assertTooManyRequests();
        $this->assertDatabaseCount('buzon_entries', 10);
    }

    public function test_public_failures_never_expose_debug_information_even_when_debug_is_enabled(): void
    {
        config(['app.debug' => true]);
        $this->app->instance(\App\Http\Controllers\PublicBuzonController::class, new class extends \App\Http\Controllers\PublicBuzonController {
            public function show(Proyecto $proyecto, string $codigo, User $recipient)
            {
                throw new \RuntimeException('DETALLE_PRIVADO_DE_PRUEBA');
            }
        });
        $this->get($this->url())->assertStatus(500)->assertSee('No fue posible procesar el formulario.')
            ->assertDontSee('DETALLE_PRIVADO_DE_PRUEBA')->assertDontSee('Stack trace')->assertDontSee('RESPONSABLE_INTERNO');
        $this->getJson($this->url())->assertStatus(500)->assertExactJson(['message' => 'No fue posible procesar el formulario. Intente más tarde.']);
    }

    public function test_expired_public_form_returns_to_public_url_without_logging_out_an_authenticated_user(): void
    {
        $this->app->instance(\App\Http\Controllers\PublicBuzonController::class, new class extends \App\Http\Controllers\PublicBuzonController {
            public function store(\App\Http\Requests\SavePublicBuzonEntryRequest $request, Proyecto $proyecto, string $codigo, User $recipient)
            {
                throw new \Illuminate\Session\TokenMismatchException;
            }
        });
        $this->actingAs($this->owner)->post($this->url(), $this->payload())->assertRedirect($this->url())
            ->assertSessionHas('public_buzon_error');
        $this->assertAuthenticatedAs($this->owner);
        $this->assertDatabaseCount('buzon_entries', 0);
    }
}
