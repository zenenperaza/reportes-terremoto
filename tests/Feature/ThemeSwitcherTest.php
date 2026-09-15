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
}
