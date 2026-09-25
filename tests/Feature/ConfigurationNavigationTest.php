<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConfigurationNavigationTest extends TestCase
{
    use RefreshDatabase;

    private function navigation(string $html): \DOMXPath
    {
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);

        return new \DOMXPath($dom);
    }

    public function test_admin_menu_places_every_destination_in_its_section_without_duplicates(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));
        $response = $this->get(route('system-configuration.index'))->assertOk();
        $xpath = $this->navigation($response->getContent());
        $sections = [
            'sidebarConfigurationProjects' => ['donantes.index', 'proyectos.index', 'sectores.index', 'indicator-groups.index', 'indicadores.index', 'actividades.index', 'servicios.index', 'place-names.index'],
            'sidebarConfigurationSettings' => ['audit-logs.index', 'backups.index', 'system-configuration.index', 'system-maintenance.index'],
            'sidebarConfigurationUsers' => ['users.index', 'user-groups.index', 'roles.index', 'permissions.index'],
        ];
        $this->assertCount(3, $xpath->query('//*[@id="sidebarConfiguration"]/ul/li'));
        $this->assertCount(1, $xpath->query('//*[@id="sidebarConfiguration" and @data-bs-parent="#navbar-nav"]'));
        $this->assertCount(1, $xpath->query('//*[@id="sidebarReports" and @data-bs-parent="#navbar-nav"]'));
        $this->assertCount(1, $xpath->query('//*[@id="sidebarCases" and @data-bs-parent="#navbar-nav"]'));
        foreach ($sections as $section => $routes) {
            $this->assertCount(1, $xpath->query('//*[@id="'.$section.'" and @data-bs-parent="#sidebarConfiguration"]'));
            $this->assertCount(1, $xpath->query('//a[@aria-controls="'.$section.'" and @data-bs-toggle="collapse"]'));
            foreach ($routes as $route) {
                $this->assertCount(1, $xpath->query('//*[@id="'.$section.'"]//a[@href="'.route($route).'"]'), $route);
                $this->assertCount(1, $xpath->query('//*[@id="navbar-nav"]//a[@href="'.route($route).'"]'), $route);
            }
        }
    }

    public function test_current_section_is_expanded_and_destination_marked_active(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));
        foreach (['donantes.index' => 'sidebarConfigurationProjects', 'system-configuration.index' => 'sidebarConfigurationSettings', 'users.index' => 'sidebarConfigurationUsers'] as $route => $section) {
            $response = $this->get(route($route))->assertOk();
            $xpath = $this->navigation($response->getContent());
            $this->assertCount(1, $xpath->query('//*[@id="'.$section.'" and contains(concat(" ", normalize-space(@class), " "), " show ")]'));
            $this->assertCount(1, $xpath->query('//a[@aria-controls="'.$section.'" and @aria-expanded="true"]'));
            $this->assertCount(1, $xpath->query('//*[@id="'.$section.'"]//a[@href="'.route($route).'" and @aria-current="page"]'));
        }
    }

    public function test_non_admins_do_not_gain_configuration_navigation_but_keep_authorized_backup_access(): void
    {
        $user = User::factory()->create(['role' => 'reporter', 'is_active' => true]);
        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertDontSee('id="sidebarConfiguration"', false);
        $user->givePermissionTo('descargar respaldos');
        $response = $this->get(route('dashboard'))->assertOk()->assertDontSee('id="sidebarConfiguration"', false);
        $xpath = $this->navigation($response->getContent());
        $this->assertCount(1, $xpath->query('//*[@id="navbar-nav"]//a[@href="'.route('backups.index').'"]'));
    }
}
