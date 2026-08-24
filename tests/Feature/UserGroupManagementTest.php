<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Municipality;
use App\Models\Parish;
use App\Models\Report;
use App\Models\Sector;
use App\Models\State;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserGroupManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_manage_groups_and_assign_one_to_a_user(): void
    {
        $administrator = User::factory()->create(['role' => 'admin']);

        $this->actingAs($administrator)->post(route('user-groups.store'), [
            'name' => 'Equipo Zulia',
            'description' => 'Registradores del proyecto en Zulia',
            'is_active' => '1',
        ])->assertRedirect(route('user-groups.index'));

        $group = UserGroup::firstOrFail();

        $this->actingAs($administrator)->post(route('users.store'), [
            'name' => 'Registrador del grupo',
            'email' => 'grupo@example.test',
            'role' => 'reporter',
            'user_group_id' => $group->id,
            'is_active' => '1',
            'password' => 'password-segura',
            'password_confirmation' => 'password-segura',
        ])->assertRedirect();

        $this->assertDatabaseHas('users', [
            'email' => 'grupo@example.test',
            'user_group_id' => $group->id,
        ]);
    }

    public function test_reporter_sees_reports_from_own_group_but_not_another_group(): void
    {
        $group = UserGroup::create(['name' => 'Equipo Lara', 'is_active' => true]);
        $otherGroup = UserGroup::create(['name' => 'Equipo Zulia', 'is_active' => true]);
        $viewer = User::factory()->create(['role' => 'reporter', 'user_group_id' => $group->id]);
        $partner = User::factory()->create(['role' => 'reporter', 'user_group_id' => $group->id]);
        $outsider = User::factory()->create(['role' => 'reporter', 'user_group_id' => $otherGroup->id]);

        $partnerReport = $this->createReport($partner, 'Lugar del mismo grupo');
        $outsiderReport = $this->createReport($outsider, 'Lugar de otro grupo');

        $visibleIds = $viewer->constrainVisibleReports(Report::query())->pluck('reports.id');

        $this->assertTrue($visibleIds->contains($partnerReport->id));
        $this->assertFalse($visibleIds->contains($outsiderReport->id));
        $this->assertTrue($viewer->canViewReport($partnerReport));
        $this->assertFalse($viewer->canViewReport($outsiderReport));
    }

    public function test_coordinator_only_sees_reports_from_own_group(): void
    {
        $group = UserGroup::create(['name' => 'Coordinaci&oacute;n Lara', 'is_active' => true]);
        $otherGroup = UserGroup::create(['name' => 'Coordinaci&oacute;n Zulia', 'is_active' => true]);
        $coordinator = User::factory()->create([
            'role' => 'coordinator',
            'user_group_id' => $group->id,
            'countrywide_access' => true,
        ]);
        $partner = User::factory()->create(['role' => 'reporter', 'user_group_id' => $group->id]);
        $outsider = User::factory()->create(['role' => 'reporter', 'user_group_id' => $otherGroup->id]);

        $partnerReport = $this->createReport($partner, 'Lugar del grupo coordinado');
        $outsiderReport = $this->createReport($outsider, 'Lugar fuera del grupo');
        $visibleIds = $coordinator->constrainVisibleReports(Report::query())->pluck('reports.id');

        $this->assertTrue($visibleIds->contains($partnerReport->id));
        $this->assertFalse($visibleIds->contains($outsiderReport->id));
        $this->assertTrue($coordinator->canViewReport($partnerReport));
        $this->assertFalse($coordinator->canViewReport($outsiderReport));
    }

    private function createReport(User $user, string $place): Report
    {
        $state = State::firstOrCreate(['code' => 'VE13'], ['name' => 'Lara']);
        $municipality = Municipality::firstOrCreate(
            ['code' => 'VE1301'],
            ['state_id' => $state->id, 'name' => 'Iribarren'],
        );
        $parish = Parish::firstOrCreate(
            ['code' => 'VE130101'],
            ['municipality_id' => $municipality->id, 'name' => 'Uni&oacute;n'],
        );
        $sector = Sector::firstOrCreate(
            ['slug' => 'proteccion'],
            ['name' => 'Protecci&oacute;n', 'sort_order' => 1],
        );
        $activity = Activity::firstOrCreate(
            ['code' => 'ACT-TEST'],
            ['sector_id' => $sector->id, 'title' => 'Actividad de prueba', 'sort_order' => 1],
        );

        return Report::create([
            'user_id' => $user->id,
            'report_date' => today(),
            'reporter_first_name' => 'Persona',
            'reporter_last_name' => 'Prueba',
            'reporter_email' => $user->email,
            'organization' => 'ASONACOP',
            'state_id' => $state->id,
            'municipality_id' => $municipality->id,
            'parish_id' => $parish->id,
            'installation_type' => 'Comunidad',
            'place_name' => $place,
            'sector_id' => $sector->id,
            'activity_id' => $activity->id,
            'recurrence_status' => 'no',
            'total_beneficiaries' => 0,
            'beneficiary_breakdown' => [],
        ]);
    }
}
