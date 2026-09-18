<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThemeSwitcherTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_layout_exposes_the_velzon_theme_switcher(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->actingAs($user)
            ->get('/panel')
            ->assertOk()
            ->assertSee('asonacop-theme-toggle', false)
            ->assertSee('Activar tema oscuro')
            ->assertSee('css/dark-theme.css', false)
            ->assertSee('js/theme-switcher.js', false);
    }

    public function test_header_and_menu_logos_provide_both_versioned_theme_images(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $response = $this->actingAs($user)->get('/panel')->assertOk();
        $lightUrl = asset('icons/asonacop-app.png').'?v='.filemtime(public_path('icons/asonacop-app.png'));
        $darkUrl = asset('icons/asonacop-app-dark.png').'?v='.filemtime(public_path('icons/asonacop-app-dark.png'));

        $this->assertSame(4, substr_count($response->getContent(), 'data-logo-light="'.$lightUrl.'"'));
        $this->assertSame(4, substr_count($response->getContent(), 'data-logo-dark="'.$darkUrl.'"'));

        // Velzon hides logo-dark in dark mode and logo-light in light mode.
        // Our single-link logos must not carry either template visibility class.
        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new \DOMXPath($document);
        $logos = $xpath->query('//a[contains(concat(" ", normalize-space(@class), " "), " horizontal-brand ") or contains(concat(" ", normalize-space(@class), " "), " sidebar-brand ")]');
        $this->assertSame(2, $logos->length);
        foreach ($logos as $logo) {
            $classes = explode(' ', $logo->getAttribute('class'));
            $this->assertNotContains('logo-dark', $classes);
            $this->assertNotContains('logo-light', $classes);
        }
    }
}
