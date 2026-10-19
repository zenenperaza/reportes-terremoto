<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Municipality;
use App\Models\Parish;
use App\Models\PlaceName;
use App\Models\Report;
use App\Models\Sector;
use App\Models\State;
use App\Models\SystemNotification;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\UserGroup;
use App\Notifications\EmailTwoFactorCodeNotification;
use App\Support\SystemNotifications;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TeamNotificationsTest extends TestCase
{
    use RefreshDatabase;
    use \Tests\Concerns\CreatesReportProjectContext;

    private function member(UserGroup $group, string $role = 'reporter'): User
    {
        return User::factory()->create(['role' => $role, 'user_group_id' => $group->id]);
    }

    public function test_permission_is_assigned_to_base_roles_without_granting_other_privileges(): void
    {
        foreach (['admin', 'coordinator', 'reporter'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->assertTrue(SystemNotifications::allowed($user));
            if ($role !== 'admin') $this->assertFalse($user->isAdministrator());
        }
    }

    public function test_confirmed_logins_notify_only_other_active_authorized_administrators(): void
    {
        $actor = User::factory()->create(['role' => 'admin', 'requires_two_factor' => false]);
        $recipient = User::factory()->create(['role' => 'admin']);
        $inactive = User::factory()->create(['role' => 'admin', 'is_active' => false]);
        $coordinator = User::factory()->create(['role' => 'coordinator']);
        $this->post(route('login.store'), ['email' => $actor->email, 'password' => 'wrong'])->assertSessionHasErrors();
        $this->assertDatabaseCount('system_notifications', 0);
        $this->post(route('login.store'), ['email' => $actor->email, 'password' => 'password'])->assertRedirect();
        $this->assertDatabaseCount('system_notifications', 1);
        $this->assertDatabaseHas('system_notifications', ['user_id' => $recipient->id, 'actor_id' => $actor->id, 'kind' => 'login']);
        $response = $this->actingAs($recipient)->postJson(route('system-maintenance.status'))->assertOk()
            ->assertJsonPath('notification_count', 1)->assertJsonPath('notifications.0.kind', 'login');
        $this->assertStringContainsString($actor->name, $response->json('notifications.0.body'));
        $this->assertArrayNotHasKey('email', $response->json('notifications.0'));
        $this->assertDatabaseMissing('system_notifications', ['user_id' => $inactive->id]);
        $this->assertDatabaseMissing('system_notifications', ['user_id' => $coordinator->id]);
    }

    public function test_two_factor_login_does_not_notify_before_the_correct_code(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        $actor = User::factory()->create(['requires_two_factor' => true]);
        $code = null;
        Notification::assertNothingSent();
        $this->post(route('login.store'), ['email' => $actor->email, 'password' => 'password'])->assertRedirect(route('two-factor.challenge'));
        Notification::assertSentTo($actor, EmailTwoFactorCodeNotification::class, function ($notification) use (&$code) {
            $code = $notification->code;
            return true;
        });
        $this->assertDatabaseCount('system_notifications', 0);
        $wrong = $code === '111111' ? '222222' : '111111';
        $this->post(route('two-factor.verify'), ['code' => $wrong])->assertSessionHasErrors('code');
        $this->assertDatabaseCount('system_notifications', 0);
        $this->post(route('two-factor.verify'), ['code' => $code])->assertRedirect();
        $this->assertDatabaseHas('system_notifications', ['user_id' => $admin->id, 'kind' => 'login']);
    }

    public function test_team_changes_are_isolated_deduplicated_and_exclude_actor_admins_and_missing_permission(): void
    {
        $a = UserGroup::create(['name' => 'Equipo A', 'is_active' => true]);
        $b = UserGroup::create(['name' => 'Equipo B', 'is_active' => true]);
        $actor = $this->member($a);
        $actor->userGroups()->attach($b);
        $reporter = $this->member($a);
        $reporter->userGroups()->attach($b);
        $coordinator = $this->member($a, 'coordinator');
        $outsider = User::factory()->create();
        $muted = $this->member($a);
        Role::findByName('reporter')->revokePermissionTo(SystemNotifications::PERMISSION);
        $reporter->givePermissionTo(SystemNotifications::PERMISSION);
        $admin = $this->member($a, 'admin');
        $report = new Report(['user_id' => $actor->id]);
        foreach (['created', 'updated', 'deleted'] as $action) SystemNotifications::reportChange($actor, $report, $action);
        $this->assertDatabaseCount('system_notifications', 6);
        foreach ([$reporter, $coordinator] as $user) {
            $pending = SystemNotifications::pending($user->fresh());
            $this->assertSame(3, $pending['notification_count']);
            $this->assertSame(['record_created', 'record_updated', 'record_deleted'], array_column($pending['notifications'], 'kind'));
        }
        foreach ([$actor, $outsider, $muted, $admin] as $user) $this->assertDatabaseMissing('system_notifications', ['user_id' => $user->id]);
    }

    public function test_group_membership_is_not_transitive_and_inactive_groups_do_not_receive_changes(): void
    {
        $a = UserGroup::create(['name' => 'A', 'is_active' => true]);
        $b = UserGroup::create(['name' => 'B', 'is_active' => true]);
        $inactive = UserGroup::create(['name' => 'Inactivo', 'is_active' => false]);
        $actor = $this->member($a);
        $bridge = $this->member($a);
        $bridge->userGroups()->attach($b);
        $outsider = $this->member($b);
        $actor->userGroups()->attach($inactive);
        $inactiveMember = $this->member($inactive);
        SystemNotifications::reportChange($actor, new Report(['user_id' => $actor->id]), 'created');
        $this->assertDatabaseCount('system_notifications', 1);
        $this->assertDatabaseHas('system_notifications', ['user_id' => $bridge->id]);
        $this->assertDatabaseMissing('system_notifications', ['user_id' => $outsider->id]);
        $this->assertDatabaseMissing('system_notifications', ['user_id' => $inactiveMember->id]);
    }

    public function test_pending_notices_recheck_permission_membership_and_record_visibility(): void
    {
        $group = UserGroup::create(['name' => 'Equipo', 'is_active' => true]);
        $actor = $this->member($group);
        $recipient = $this->member($group, 'coordinator');
        SystemNotifications::reportChange($actor, new Report(['user_id' => $actor->id]), 'created');
        $this->assertSame(1, SystemNotifications::pending($recipient)['notification_count']);
        $recipient->userGroups()->detach();
        $this->assertSame(0, SystemNotifications::pending($recipient->fresh())['notification_count']);
        $recipient->userGroups()->attach($group);
        Role::findByName('coordinator')->revokePermissionTo(SystemNotifications::PERMISSION);
        $payload = SystemNotifications::pending($recipient->fresh());
        $this->assertFalse($payload['can_receive_notifications']);
        $this->assertSame([], $payload['notifications']);
        $outsider = User::factory()->create();
        Role::findByName('coordinator')->givePermissionTo(SystemNotifications::PERMISSION);
        SystemNotifications::reportChange($actor, new Report(['user_id' => $outsider->id]), 'updated');
        $this->assertDatabaseCount('system_notifications', 1);
    }

    public function test_read_acknowledgments_are_authenticated_permission_checked_and_recipient_scoped(): void
    {
        $actor = User::factory()->create();
        $first = User::factory()->create(['role' => 'admin']);
        $second = User::factory()->create(['role' => 'admin']);
        SystemNotifications::login($actor);
        $own = SystemNotification::where('user_id', $first->id)->firstOrFail();
        $other = SystemNotification::where('user_id', $second->id)->firstOrFail();
        $this->postJson(route('system-notifications.read'), ['ids' => [$own->id]])->assertUnauthorized();
        $count = DB::table('audit_logs')->count();
        SystemSetting::create(['key' => SystemSetting::MAINTENANCE_MODE, 'value' => '1']);
        $this->actingAs($first)->postJson(route('system-notifications.read'), ['ids' => [$own->id, $other->id]])->assertNoContent();
        $this->assertNotNull($own->fresh()->read_at);
        $this->assertNull($other->fresh()->read_at);
        $this->assertSame($count, DB::table('audit_logs')->count());
        Role::findByName('admin')->revokePermissionTo(SystemNotifications::PERMISSION);
        $this->actingAs($first->fresh())->postJson(route('system-notifications.read'), ['ids' => [$own->id]])->assertForbidden();
    }

    public function test_missing_notification_table_does_not_break_operations_and_old_events_do_not_replay(): void
    {
        $actor = User::factory()->create();
        $admin = User::factory()->create(['role' => 'admin']);
        SystemNotifications::login($actor);
        SystemNotification::query()->update(['created_at' => now()->subDays(2)]);
        $this->assertSame(0, SystemNotifications::pending($admin)['notification_count']);
        Schema::drop('system_notifications');
        SystemNotifications::login($actor);
        $this->actingAs($admin)->postJson(route('system-maintenance.status'))->assertOk()->assertJsonPath('notifications', []);
    }

    public function test_notification_rows_roll_back_with_an_enclosing_transaction(): void
    {
        $actor = User::factory()->create();
        User::factory()->create(['role' => 'admin']);
        DB::beginTransaction();
        SystemNotifications::login($actor);
        $this->assertDatabaseCount('system_notifications', 1);
        DB::rollBack();
        $this->assertDatabaseCount('system_notifications', 0);
    }

    public function test_real_beneficiary_create_update_delete_emit_events_only_on_success(): void
    {
        $group = UserGroup::create(['name' => 'Equipo', 'is_active' => true]);
        $actor = $this->member($group);
        $recipient = $this->member($group, 'coordinator');
        $actor->givePermissionTo(['editar beneficiarios', 'eliminar beneficiarios']);
        $state = State::create(['code' => 'VE01', 'name' => 'Estado']);
        $municipality = Municipality::create(['state_id' => $state->id, 'code' => 'M01', 'name' => 'Municipio']);
        $parish = Parish::create(['municipality_id' => $municipality->id, 'code' => 'P01', 'name' => 'Parroquia']);
        $sector = Sector::create(['name' => 'Protección', 'slug' => 'proteccion']);
        $activity = Activity::create(['sector_id' => $sector->id, 'code' => 'TEST', 'title' => 'Prueba']);
        PlaceName::create(['name' => 'Comunidad']);
        $header = ['report_date' => today()->toDateString(), 'reporter_first_name' => 'Persona', 'reporter_last_name' => 'Equipo',
            'reporter_email' => $actor->email, 'organization' => 'ASONACOP', 'state_id' => $state->id,
            'municipality_id' => $municipality->id, 'parish_id' => $parish->id,
            'installation_type' => 'Comunidad / Espacio Comunitario', 'place_name' => 'Comunidad',
            'sector_id' => $sector->id, 'activity_id' => $activity->id]
            + $this->reportProjectContext($actor, $state, $sector);
        $person = ['has_informed_consent' => true, 'full_name' => 'PERSONA PRIVADA', 'age' => 12, 'sex' => 'Mujer',
            'national_id' => 'V12345678', 'disability' => 'Ninguna', 'ethnicity' => 'Ninguna', 'pregnant_lactating' => 'N/A', 'is_recurrent' => false];
        $this->actingAs($actor)->postJson(route('beneficiaries.store'), $header + ['beneficiary' => []])->assertUnprocessable();
        $this->assertDatabaseCount('system_notifications', 0);
        $response = $this->postJson(route('beneficiaries.store'), $header + ['beneficiary' => $person])->assertCreated();
        $beneficiaryId = $response->json('beneficiary.id');
        $reportId = $response->json('report.id');
        $this->assertDatabaseHas('system_notifications', ['user_id' => $recipient->id, 'kind' => 'record_created']);
        $this->putJson(route('beneficiaries.update', $beneficiaryId), $person + ['phone' => '04140000000'])->assertOk();
        $this->postJson(route('beneficiaries.store'), $header + ['report_id' => $reportId, 'beneficiary' => $person])->assertOk();
        $this->deleteJson(route('beneficiaries.destroy', $beneficiaryId))->assertOk();
        $this->assertDatabaseHas('system_notifications', ['user_id' => $recipient->id, 'kind' => 'record_updated']);
        $this->assertDatabaseHas('system_notifications', ['user_id' => $recipient->id, 'kind' => 'record_deleted']);
        $payload = SystemNotifications::pending($recipient);
        $this->assertStringNotContainsString('PERSONA PRIVADA', json_encode($payload));
        $this->assertStringNotContainsString('V12345678', json_encode($payload));
    }
}
