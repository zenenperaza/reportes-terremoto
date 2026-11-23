<?php

namespace Tests\Feature;

use App\Models\{Donante, Proyecto, User};
use App\Support\DonorLogo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Event, Storage};
use Tests\TestCase;

class DonorLogoTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($this->admin);
    }

    private function payload(): array
    {
        return ['nombre' => 'Donante de prueba', 'estatus' => '1', 'enlaces' => 'https://example.test'];
    }

    private function withLogo(): Donante
    {
        $this->post(route('donantes.store'), $this->payload() + ['logo' => UploadedFile::fake()->image('logo.png', 160, 100)])
            ->assertSessionHasNoErrors()->assertRedirect(route('donantes.index'));
        return Donante::where('nombre', 'Donante de prueba')->sole();
    }

    private function project(Donante $donor, string $code = 'LOGO-TEST'): Proyecto
    {
        return Proyecto::create(['donante_id' => $donor->id, 'codigo' => $code, 'descripcion' => 'Proyecto de prueba', 'estatus' => true]);
    }

    public function test_admin_can_upload_a_private_logo_in_donor_create_and_edit(): void
    {
        $donor = $this->withLogo();
        $this->assertTrue(DonorLogo::validPath($donor->logo_path));
        Storage::disk('local')->assertExists($donor->logo_path);
        $this->assertSame('https://example.test', $donor->enlaces);
        $this->assertArrayNotHasKey('logo_path', $donor->toArray());
        $this->get(DonorLogo::url($donor))->assertOk()->assertHeader('Content-Type', 'image/png')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get(route('donantes.create'))->assertOk()->assertSee('enctype="multipart/form-data"', false)->assertSee('name="logo"', false);
        $edit = $this->get(route('donantes.edit', $donor))->assertOk()->assertSee('Quitar logo actual')->assertSee('Logo actual del donante');
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$edit->getContent());
        $this->assertSame(1, (new \DOMXPath($dom))->query('//head/link[contains(@href,"donor-logo.css")]')->length);
        $this->get(route('proyectos.create'))->assertOk()->assertDontSee('name="logo"', false);
    }

    public function test_update_preserves_replaces_and_removes_logo_only_on_explicit_changes(): void
    {
        $donor = $this->withLogo(); $old = $donor->logo_path;
        $this->put(route('donantes.update', $donor), $this->payload())->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame($old, $donor->fresh()->logo_path);
        Storage::disk('local')->assertExists($old);
        $this->put(route('donantes.update', $donor), $this->payload() + ['logo' => UploadedFile::fake()->image('reemplazo.jpg', 100, 160)])
            ->assertSessionHasNoErrors()->assertRedirect();
        $new = $donor->fresh()->logo_path;
        $this->assertNotSame($old, $new);
        Storage::disk('local')->assertMissing($old); Storage::disk('local')->assertExists($new);
        $this->put(route('donantes.update', $donor), $this->payload() + ['remove_logo' => '1'])->assertRedirect();
        $this->assertNull($donor->fresh()->logo_path);
        Storage::disk('local')->assertMissing($new);
        $this->assertNull(DonorLogo::url($donor->fresh()));
    }

    public function test_upload_and_remove_together_are_rejected_without_deleting_existing_logo(): void
    {
        $donor = $this->withLogo();
        $this->putJson(route('donantes.update', $donor), $this->payload() + [
            'remove_logo' => true, 'logo' => UploadedFile::fake()->image('new.png', 100, 100),
        ])->assertUnprocessable()->assertJsonValidationErrors('logo');
        Storage::disk('local')->assertExists($donor->logo_path);
        $this->assertCount(1, Storage::disk('local')->allFiles('donor-logos'));
    }

    public function test_invalid_formats_sizes_dimensions_and_spoofed_file_extensions_are_rejected(): void
    {
        foreach ([
            UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
            UploadedFile::fake()->createWithContent('logo.png', 'not an image'),
            UploadedFile::fake()->image('logo.php', 100, 100),
            UploadedFile::fake()->image('huge.png', 100, 100)->size(2049),
            UploadedFile::fake()->image('tiny.png', 31, 100),
            UploadedFile::fake()->image('wide.png', 2001, 40),
        ] as $logo) {
            $this->postJson(route('donantes.store'), $this->payload() + ['logo' => $logo])->assertUnprocessable()->assertJsonValidationErrors('logo');
        }
        $this->assertDatabaseCount('donantes', 0);
        $this->assertSame([], Storage::disk('local')->allFiles('donor-logos'));
    }

    public function test_private_logo_requires_admin_or_buzon_permissions_and_project_assignment(): void
    {
        $donor = $this->withLogo(); $url = DonorLogo::url($donor);
        $project = $this->project($donor);
        auth()->logout();
        $this->get($url)->assertRedirect(route('login'));
        $reporter = User::factory()->create(['role' => 'reporter']);
        $reporter->projects()->attach($project);
        $this->actingAs($reporter)->getJson($url)->assertNotFound();
        $reporter->givePermissionTo(['ver buzon', 'crear buzon']);
        $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->get(route('buzon.public.index'))->assertOk()->assertSee('data-project-logo="'.e($url).'"', false);
        $reporter->projects()->detach($project);
        $otherDonor = Donante::create(['nombre' => 'Otro donante', 'estatus' => true]);
        $reporter->projects()->attach($this->project($otherDonor, 'OTHER'));
        $this->getJson($url)->assertNotFound();
        $this->actingAs($this->admin)->get($url)->assertOk();
        Storage::disk('local')->delete($donor->logo_path);
        $this->getJson($url)->assertNotFound();
    }

    public function test_projects_share_donor_logo_without_changing_project_data_or_links(): void
    {
        $donor = $this->withLogo();
        $first = $this->project($donor, 'FIRST');
        $second = $this->project($donor, 'SECOND');
        $other = Donante::create(['nombre' => 'Sin logo', 'estatus' => true]);
        $third = $this->project($other, 'THIRD');
        $url = DonorLogo::url($donor);
        $firstLink = \App\Support\PublicBuzonLink::url($first, $this->admin);
        $this->get(route('buzon.public.index'))->assertOk()->assertSee('data-project-logo="'.e($url).'"', false);
        $this->assertSame($url, DonorLogo::url($first->donante));
        $this->assertSame($url, DonorLogo::url($second->donante));
        $this->assertNull(DonorLogo::url($third->donante));
        $this->put(route('donantes.update', $donor), $this->payload() + ['logo' => UploadedFile::fake()->image('new.png', 80, 80)])
            ->assertSessionHasNoErrors()->assertRedirect();
        $newUrl = DonorLogo::url($donor->fresh());
        $this->assertNotSame($url, $newUrl);
        $html = $this->get(route('buzon.public.index'))->assertOk()->getContent();
        $this->assertSame(2, substr_count($html, 'data-project-logo="'.e($newUrl).'"'));
        $this->assertSame(2, substr_count($html, 'data-donor-name="'.e($donor->nombre).'"'));
        $this->assertStringContainsString('data-public-donor-preview', $html);
        $this->assertStringContainsString('js/buzon-share-project.js', $html);
        $this->assertSame($firstLink, \App\Support\PublicBuzonLink::url($first->fresh(), $this->admin));
        $this->assertDatabaseHas('proyectos', ['id' => $first->id, 'codigo' => 'FIRST', 'donante_id' => $donor->id]);
        $this->get(route('proyectos.edit', $first))->assertOk()->assertDontSee('name="logo"', false);
    }

    public function test_non_admin_cannot_upload_or_remove_and_path_input_cannot_choose_files(): void
    {
        $donor = $this->withLogo();
        $this->put(route('donantes.update', $donor), $this->payload() + ['logo_path' => '../../private-file.png'])->assertRedirect();
        $this->assertSame($donor->logo_path, $donor->fresh()->logo_path);
        $reporter = User::factory()->create(['role' => 'reporter']);
        $this->actingAs($reporter)->putJson(route('donantes.update', $donor), $this->payload() + ['remove_logo' => true])->assertForbidden();
        $this->postJson(route('donantes.store'), $this->payload() + ['logo' => UploadedFile::fake()->image('fake.png', 80, 80)])->assertForbidden();
        Storage::disk('local')->assertExists($donor->logo_path);
        $donor->forceFill(['logo_path' => '../private-file.png'])->save();
        $this->actingAs($this->admin)->getJson(route('donantes.logo', $donor))->assertNotFound();
        $this->assertNull(DonorLogo::url($donor));
    }

    public function test_failed_creation_cleans_new_file_and_failed_update_keeps_previous_logo(): void
    {
        Event::listen('eloquent.created: '.Donante::class, fn () => throw new \RuntimeException('Controlled creation failure'));
        $this->withoutExceptionHandling();
        try {
            $this->post(route('donantes.store'), $this->payload() + ['logo' => UploadedFile::fake()->image('new.png', 100, 100)]);
            $this->fail('Expected failure.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Controlled creation failure', $exception->getMessage());
        } finally {Event::forget('eloquent.created: '.Donante::class);}
        $this->assertDatabaseCount('donantes', 0);
        $this->assertSame([], Storage::disk('local')->allFiles('donor-logos'));
        $donor = $this->withLogo();
        Event::listen('eloquent.updated: '.Donante::class, fn () => throw new \RuntimeException('Controlled update failure'));
        try {
            $this->put(route('donantes.update', $donor), $this->payload() + ['logo' => UploadedFile::fake()->image('new.png', 100, 100)]);
            $this->fail('Expected failure.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Controlled update failure', $exception->getMessage());
        } finally {Event::forget('eloquent.updated: '.Donante::class);}
        $this->assertSame($donor->logo_path, $donor->fresh()->logo_path);
        Storage::disk('local')->assertExists($donor->logo_path);
        $this->assertSame([$donor->logo_path], Storage::disk('local')->allFiles('donor-logos'));
    }

    public function test_donor_cannot_be_deleted_with_projects_and_successful_deletion_cleans_logo(): void
    {
        $donor = $this->withLogo();
        $project = $this->project($donor);
        $this->delete(route('donantes.destroy', $donor))->assertRedirect()->assertSessionHas('error');
        Storage::disk('local')->assertExists($donor->logo_path);
        $project->delete();
        $this->delete(route('donantes.destroy', $donor))->assertRedirect(route('donantes.index'));
        Storage::disk('local')->assertMissing($donor->logo_path);
        $this->assertDatabaseMissing('donantes', ['id' => $donor->id]);
    }
}
