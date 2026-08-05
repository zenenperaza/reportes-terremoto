<?php

namespace Tests\Feature;

use Tests\TestCase;

class ProgressiveWebAppTest extends TestCase
{
    public function test_pwa_assets_exist_and_manifest_is_valid(): void
    {
        $manifestPath = public_path('manifest.webmanifest');

        $this->assertFileExists($manifestPath);
        $this->assertFileExists(public_path('service-worker.js'));
        $this->assertFileExists(public_path('offline.html'));
        $this->assertFileExists(public_path('js/pwa.js'));
        $this->assertFileExists(public_path('icons/asonacop-app.png'));

        $manifest = json_decode(file_get_contents($manifestPath), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('standalone', $manifest['display']);
        $this->assertSame('/panel?source=pwa', $manifest['start_url']);
        $this->assertNotEmpty($manifest['icons']);
    }

    public function test_login_page_exposes_pwa_metadata(): void
    {
        $this->get('/ingresar')
            ->assertOk()
            ->assertSee('manifest.webmanifest', false)
            ->assertSee('js/pwa.js', false)
            ->assertSee('theme-color', false);
    }
}
