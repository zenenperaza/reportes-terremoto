<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_button_is_kept_in_records_but_not_in_global_navigation(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $response = $this->actingAs($admin)->get(route('reports.index'))->assertOk();
        $dom = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new \DOMXPath($dom);
        $create = route('reports.create');
        $index = route('reports.index');
        $this->assertSame(0, $xpath->query('//*[@id="page-topbar"]//a[@href="'.$create.'"]')->length);
        $this->assertSame(0, $xpath->query('//*[@id="navbar-nav"]//a[@href="'.$create.'"]')->length);
        $this->assertSame(1, $xpath->query('//*[@id="navbar-nav"]//a[@href="'.$index.'"]')->length);
        $this->assertSame(1, $xpath->query('//a[@href="'.$create.'"]')->length);
        $response->assertSee('+ Nuevo registro')->assertSee('Registros de actividades');
    }

    public function test_horizontal_menu_loads_its_controller_and_accessible_target(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('reports.index'))->assertOk()
            ->assertSee('id="main-navigation"', false)
            ->assertSee('aria-controls="main-navigation"', false)
            ->assertSee('js/horizontal-menu.js?v=', false);
    }
}
