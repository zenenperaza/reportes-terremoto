<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class CaseManagementNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrators_can_open_cases_and_families_from_the_menu(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $user->givePermissionTo('ver casos');
        $this->actingAs($user)->get(route('beneficiaries.summary'))->assertOk()
            ->assertSee('id="sidebarCases"', false)
            ->assertSee('href="'.route('cases.index').'"', false)
            ->assertSee('href="'.route('families.index').'"', false)
            ->assertDontSee('Sección en construcción');
        $this->get(route('cases.index'))->assertOk();
        $this->get(route('families.index'))->assertOk();
    }

    public function test_case_permissions_do_not_grant_non_administrators_access_to_any_module_route(): void
    {
        $case = \App\Models\CaseRecord::create(['reference' => 'CS-TEST', 'full_name' => 'Caso de prueba', 'registered_on' => today()]);
        $family = \App\Models\FamilyRecord::create(['reference' => 'FM-TEST', 'name' => 'Familia de prueba', 'registered_on' => today()]);
        $attachment = \App\Models\CaseAttachment::create(['case_record_id' => $case->id, 'category' => 'document', 'path' => 'private/test.pdf', 'original_name' => 'test.pdf', 'mime' => 'application/pdf', 'size' => 1]);
        $bindings = ['caseRecord' => $case, 'familyRecord' => $family, 'attachment' => $attachment];
        foreach (['reporter', 'coordinator'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $user->givePermissionTo(['ver casos', 'crear casos', 'editar casos', 'asignar casos', 'supervisar casos', 'ver historial de casos', 'gestionar casos vbg']);
            $this->actingAs($user)->get(route('beneficiaries.summary'))->assertOk()
                ->assertDontSee('id="sidebarCases"', false);

            foreach (Route::getRoutes() as $route) {
                if (! str_starts_with($route->getName() ?? '', 'cases.') && ! str_starts_with($route->getName() ?? '', 'families.')) {
                    continue;
                }
                $parameters = array_intersect_key($bindings, array_flip($route->parameterNames()));
                foreach (array_diff($route->methods(), ['HEAD']) as $method) {
                    $this->call($method, route($route->getName(), $parameters))->assertForbidden();
                }
            }
        }
    }

    public function test_administrator_still_requires_case_permissions(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $user->syncRoles([]);
        $user->syncPermissions([]);
        $this->actingAs($user)->get(route('cases.index'))->assertForbidden();
        $this->get(route('families.index'))->assertForbidden();
        $this->get(route('beneficiaries.summary'))->assertOk()->assertDontSee('id="sidebarCases"', false);
    }
}
