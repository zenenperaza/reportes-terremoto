<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CaseManagementNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_case_menu_shows_construction_notice_instead_of_case_and_family_links(): void
    {
        foreach (['admin', 'reporter'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $user->givePermissionTo('ver casos');
            $response = $this->actingAs($user)->get(route('beneficiaries.summary'))->assertOk()
                ->assertSee('Gestión de casos')->assertSee('Sección en construcción');
            $previous = libxml_use_internal_errors(true);
            $dom = new \DOMDocument;
            $dom->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
            $xpath = new \DOMXPath($dom);
            $menu = $xpath->query('//div[@id="sidebarCases"]');
            $this->assertCount(1, $menu);
            $this->assertCount(0, $xpath->query('.//a', $menu->item(0)));
            $notice = $xpath->query('.//span[@role="status"]', $menu->item(0));
            $this->assertCount(1, $notice);
            $this->assertSame('Sección en construcción', $notice->item(0)->textContent);
            $this->assertCount(1, $xpath->query('//a[@aria-controls="sidebarCases"]'));
            $this->assertGreaterThan(0, $xpath->query('//div[@id="sidebarReports"]//a')->length);
        }
    }

    public function test_construction_notice_does_not_bypass_menu_permissions(): void
    {
        $user = User::factory()->create(['role' => 'reporter']);
        $this->assertFalse($user->can('ver casos'));
        $this->actingAs($user)->get(route('beneficiaries.summary'))->assertOk()
            ->assertDontSee('id="sidebarCases"', false)->assertDontSee('Sección en construcción');
    }
}
