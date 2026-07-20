<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Beneficiary;
use App\Models\Evidence;
use App\Models\Municipality;
use App\Models\Parish;
use App\Models\Report;
use App\Models\Sector;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReportWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login_and_can_see_login_form(): void
    {
        $this->get('/')->assertRedirect('/panel');
        $this->get('/ingresar')->assertOk()->assertSee('Ingrese a su cuenta');
    }

    public function test_authenticated_user_can_submit_a_report_with_individual_beneficiaries(): void
    {
        $user = User::factory()->create();
        $state = State::create(['code' => 'VE01', 'name' => 'Distrito Capital']);
        $municipality = Municipality::create(['state_id' => $state->id, 'code' => 'VE0101', 'name' => 'Libertador']);
        $parish = Parish::create(['municipality_id' => $municipality->id, 'code' => 'VE010101', 'name' => 'Altagracia']);
        $sector = Sector::create(['name' => 'Protección de la niñez', 'slug' => 'proteccion-ninez', 'sort_order' => 1]);
        $activity = Activity::create(['sector_id' => $sector->id, 'code' => 'TEST-01', 'title' => 'Actividad de prueba', 'sort_order' => 1]);
        Storage::fake('local');

        $this->actingAs($user)->get('/reportes/nuevo')->assertOk()->assertSee('Registrar beneficiario')->assertDontSee('Enviar reporte');

        $response = $this->actingAs($user)->post('/reportes', [
            'report_date' => today()->toDateString(),
            'reporter_first_name' => 'Ana',
            'reporter_last_name' => 'Pérez',
            'reporter_email' => 'ana@example.test',
            'organization' => 'ASONACOP',
            'state_id' => $state->id,
            'municipality_id' => $municipality->id,
            'parish_id' => $parish->id,
            'installation_type' => 'Comunidad / Espacio Comunitario',
            'place_name' => 'Comunidad El Carmen',
            'sector_id' => $sector->id,
            'activity_id' => $activity->id,
            'beneficiaries' => [
                [
                    'full_name' => 'María Gómez', 'age' => 8, 'sex' => 'Mujer', 'national_id' => 'V12345678',
                    'phone' => '04141234567', 'disability' => 'Ninguna', 'ethnicity' => 'Ninguna',
                    'pregnant_lactating' => 'N/A', 'is_recurrent' => 0,
                ],
                [
                    'full_name' => 'Carla Rojas', 'age' => 28, 'sex' => 'Mujer', 'national_id' => 'V87654321',
                    'phone' => '04149876543', 'disability' => 'Discapacidad Física o Motora', 'ethnicity' => 'Wayúu',
                    'pregnant_lactating' => 'Sí', 'is_recurrent' => 1,
                ],
            ],
            'qualitative_notes' => 'Actividad realizada sin novedades.',
            'evidence_1' => UploadedFile::fake()->create('evidencia.pdf', 100, 'application/pdf'),
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('reports', [
            'place_name' => 'Comunidad El Carmen', 'total_beneficiaries' => 2, 'recurrence_status' => 'mixto',
            'people_with_disabilities' => 1, 'indigenous_people' => 1, 'pregnant_or_lactating_women' => 1,
        ]);
        $this->assertDatabaseHas('beneficiaries', ['full_name' => 'Carla Rojas', 'ethnicity' => 'Wayúu', 'is_recurrent' => 1]);
        $this->assertDatabaseHas('evidences', ['original_name' => 'evidencia.pdf', 'slot' => 1]);
        Storage::disk('local')->assertExists(Evidence::firstOrFail()->path);
        $this->get($response->headers->get('Location'))->assertOk()->assertSee('Actividad de prueba')->assertSee('Carla Rojas');
        $this->get('/panel')->assertOk()->assertSee('Mis actividades registradas');
    }

    public function test_administrator_can_create_update_and_delete_users(): void
    {
        $administrator = User::factory()->create(['role' => 'admin']);

        $this->actingAs($administrator)->get('/usuarios')
            ->assertOk()
            ->assertSee('Usuarios del sistema');

        $this->actingAs($administrator)->post('/usuarios', [
            'name' => 'Mariana Rodríguez',
            'email' => 'mariana@example.test',
            'role' => 'reporter',
            'password' => 'password-segura',
            'password_confirmation' => 'password-segura',
        ])->assertRedirect();

        $managedUser = User::where('email', 'mariana@example.test')->firstOrFail();
        $this->assertDatabaseHas('users', ['id' => $managedUser->id, 'role' => 'reporter']);

        $this->actingAs($administrator)->put("/usuarios/{$managedUser->id}", [
            'name' => 'Mariana Rodríguez Pérez',
            'email' => 'mariana@example.test',
            'role' => 'coordinator',
            'password' => '',
            'password_confirmation' => '',
        ])->assertRedirect('/usuarios');

        $this->assertDatabaseHas('users', ['id' => $managedUser->id, 'role' => 'coordinator']);

        $this->actingAs($administrator)->delete("/usuarios/{$managedUser->id}")
            ->assertRedirect('/usuarios');

        $this->assertSoftDeleted('users', ['id' => $managedUser->id]);
    }

    public function test_reporter_cannot_access_user_administration(): void
    {
        $reporter = User::factory()->create(['role' => 'reporter']);

        $this->actingAs($reporter)->get('/usuarios')->assertForbidden();
    }

    public function test_reports_and_beneficiary_summary_are_scoped_to_the_user_except_for_administrators(): void
    {
        $owner = User::factory()->create(['role' => 'reporter']);
        $otherUser = User::factory()->create(['role' => 'reporter']);
        $administrator = User::factory()->create(['role' => 'admin']);
        $state = State::create(['code' => 'VE01', 'name' => 'Distrito Capital']);
        $municipality = Municipality::create(['state_id' => $state->id, 'code' => 'VE0101', 'name' => 'Libertador']);
        $parish = Parish::create(['municipality_id' => $municipality->id, 'code' => 'VE010101', 'name' => 'Altagracia']);
        $differentParish = Parish::create(['municipality_id' => $municipality->id, 'code' => 'VE010102', 'name' => 'Catedral']);
        $sector = Sector::create(['name' => 'Protección', 'slug' => 'proteccion', 'sort_order' => 1]);
        $activity = Activity::create(['sector_id' => $sector->id, 'code' => 'TEST-01', 'title' => 'Actividad de prueba', 'sort_order' => 1]);

        $ownReport = $this->makeReport($owner, $state, $municipality, $parish, $sector, $activity, 'Lugar de Ana');
        $otherReport = $this->makeReport($otherUser, $state, $municipality, $parish, $sector, $activity, 'Lugar de Luis');
        Beneficiary::create(['report_id' => $ownReport->id, 'full_name' => 'Ana Niño', 'age' => 4, 'sex' => 'Mujer', 'national_id' => 'V-123', 'disability' => 'Ninguna', 'ethnicity' => 'Ninguna', 'pregnant_lactating' => 'N/A', 'is_recurrent' => false]);
        Beneficiary::create(['report_id' => $otherReport->id, 'full_name' => 'Luis Mayor', 'age' => 65, 'sex' => 'Hombre', 'national_id' => null, 'disability' => 'Ninguna', 'ethnicity' => 'Ninguna', 'pregnant_lactating' => 'N/A', 'is_recurrent' => true]);

        $this->actingAs($owner)->get("/reportes/{$ownReport->id}")->assertOk();
        $this->actingAs($owner)->get("/reportes/{$otherReport->id}")->assertForbidden();
        $this->actingAs($administrator)->get('/reportes')->assertOk()->assertSee($owner->name)->assertSee($otherUser->name);

        $this->actingAs($owner)->get('/informe-beneficiarios')->assertOk()->assertSee('1 registro coincide');
        $this->actingAs($administrator)->get('/informe-beneficiarios')->assertOk()->assertSee('2 registros coinciden');
        $this->actingAs($administrator)->get('/informe-beneficiarios?is_recurrent=1')->assertOk()->assertSee('1 registro coincide');

        $this->actingAs($owner)->getJson("/beneficiarios/verificar-recurrencia?activity_id={$activity->id}&national_id=V123")
            ->assertOk()
            ->assertJson(['possible_match' => true, 'matches' => 1]);
        $this->actingAs($owner)->getJson("/beneficiarios/verificar-recurrencia?activity_id={$activity->id}&state_id={$state->id}&municipality_id={$municipality->id}&parish_id={$parish->id}&full_name=Ana%20Ni%C3%B1o&age=4&sex=Mujer")
            ->assertOk()
            ->assertJson(['possible_match' => true, 'matches' => 1]);
        $this->actingAs($owner)->getJson("/beneficiarios/verificar-recurrencia?activity_id={$activity->id}&state_id={$state->id}&municipality_id={$municipality->id}&parish_id={$differentParish->id}&full_name=Ana%20Ni%C3%B1o&age=4&sex=Mujer")
            ->assertOk()
            ->assertJson(['possible_match' => false, 'matches' => 0]);
    }

    public function test_each_beneficiary_is_saved_immediately_under_the_same_report_header(): void
    {
        $user = User::factory()->create(['role' => 'reporter']);
        $state = State::create(['code' => 'VE01', 'name' => 'Distrito Capital']);
        $municipality = Municipality::create(['state_id' => $state->id, 'code' => 'VE0101', 'name' => 'Libertador']);
        $parish = Parish::create(['municipality_id' => $municipality->id, 'code' => 'VE010101', 'name' => 'Altagracia']);
        $sector = Sector::create(['name' => 'Protección', 'slug' => 'proteccion', 'sort_order' => 1]);
        $activity = Activity::create(['sector_id' => $sector->id, 'code' => 'TEST-01', 'title' => 'Actividad de prueba', 'sort_order' => 1]);
        $header = [
            'report_date' => today()->toDateString(), 'reporter_first_name' => 'Ana', 'reporter_last_name' => 'Pérez',
            'reporter_email' => 'ana@example.test', 'organization' => 'ASONACOP', 'state_id' => $state->id,
            'municipality_id' => $municipality->id, 'parish_id' => $parish->id,
            'installation_type' => 'Comunidad / Espacio Comunitario', 'place_name' => 'Comunidad El Carmen',
            'sector_id' => $sector->id, 'activity_id' => $activity->id,
        ];

        $first = $this->actingAs($user)->postJson('/beneficiarios', $header + ['beneficiary' => [
            'full_name' => 'María Gómez', 'age' => 8, 'sex' => 'Mujer', 'national_id' => 'V12345678', 'phone' => null,
            'disability' => 'Ninguna', 'ethnicity' => 'Ninguna', 'pregnant_lactating' => 'N/A', 'is_recurrent' => false,
        ]])->assertCreated()->assertJsonPath('summary.total', 1);

        $reportId = $first->json('report.id');
        $this->assertDatabaseHas('reports', ['id' => $reportId, 'user_id' => $user->id, 'total_beneficiaries' => 1]);
        $this->assertDatabaseHas('beneficiaries', ['report_id' => $reportId, 'full_name' => 'María Gómez']);

        $this->actingAs($user)->postJson('/beneficiarios', $header + ['report_id' => $reportId, 'beneficiary' => [
            'full_name' => 'Carlos Ruiz', 'age' => 34, 'sex' => 'Hombre', 'national_id' => null, 'phone' => '04140000000',
            'disability' => 'Ninguna', 'ethnicity' => 'Ninguna', 'pregnant_lactating' => 'N/A', 'is_recurrent' => true,
        ]])->assertOk()->assertJsonPath('summary.total', 2);

        $this->assertDatabaseCount('reports', 1);
        $this->assertDatabaseCount('beneficiaries', 2);
        $this->assertDatabaseHas('reports', ['id' => $reportId, 'total_beneficiaries' => 2, 'recurrence_status' => 'mixto']);

        $changedHeader = $header;
        $changedHeader['place_name'] = 'Comunidad El Manantial';
        $this->actingAs($user)->postJson('/beneficiarios', $changedHeader + ['beneficiary' => [
            'full_name' => 'Rosa Díaz', 'age' => 40, 'sex' => 'Mujer', 'national_id' => null, 'phone' => null,
            'disability' => 'Ninguna', 'ethnicity' => 'Ninguna', 'pregnant_lactating' => 'N/A', 'is_recurrent' => false,
        ]])->assertCreated()->assertJsonPath('summary.total', 1);

        $this->assertDatabaseCount('reports', 2);
        $this->assertDatabaseHas('reports', ['id' => $reportId, 'place_name' => 'Comunidad El Carmen', 'total_beneficiaries' => 2]);
        $this->assertDatabaseHas('reports', ['place_name' => 'Comunidad El Manantial', 'total_beneficiaries' => 1]);
    }

    public function test_user_can_mark_filtered_beneficiaries_as_reported_without_affecting_other_users(): void
    {
        $owner = User::factory()->create(['role' => 'reporter']);
        $otherUser = User::factory()->create(['role' => 'reporter']);
        $state = State::create(['code' => 'VE01', 'name' => 'Distrito Capital']);
        $municipality = Municipality::create(['state_id' => $state->id, 'code' => 'VE0101', 'name' => 'Libertador']);
        $parish = Parish::create(['municipality_id' => $municipality->id, 'code' => 'VE010101', 'name' => 'Altagracia']);
        $sector = Sector::create(['name' => 'Protección', 'slug' => 'proteccion', 'sort_order' => 1]);
        $activity = Activity::create(['sector_id' => $sector->id, 'code' => 'TEST-01', 'title' => 'Actividad de prueba', 'sort_order' => 1]);
        $ownReport = $this->makeReport($owner, $state, $municipality, $parish, $sector, $activity, 'Lugar de Ana');
        $otherReport = $this->makeReport($otherUser, $state, $municipality, $parish, $sector, $activity, 'Lugar de otra persona');
        $ownBeneficiary = $ownReport->beneficiaries()->create(['full_name' => 'Ana Niño', 'age' => 10, 'sex' => 'Mujer', 'is_recurrent' => false]);
        $otherBeneficiary = $otherReport->beneficiaries()->create(['full_name' => 'Otra Niña', 'age' => 8, 'sex' => 'Mujer', 'is_recurrent' => false]);

        $this->actingAs($owner)->get('/informe-beneficiarios?reported=0')
            ->assertOk()
            ->assertSee('Actualizar a Reportado');

        $this->actingAs($owner)->post('/informe-beneficiarios/marcar-reportados', ['reported' => '0'])
            ->assertRedirect()
            ->assertSessionHas('success', '1 beneficiario fue actualizado como reportado.');

        $this->assertDatabaseHas('beneficiaries', ['id' => $ownBeneficiary->id, 'reported' => true, 'reported_at' => today()->toDateString()]);
        $this->assertDatabaseHas('beneficiaries', ['id' => $otherBeneficiary->id, 'reported' => false, 'reported_at' => null]);

        $this->actingAs($owner)->get('/reportes?reported=1')
            ->assertOk()
            ->assertSee(route('reports.show', $ownReport))
            ->assertDontSee(route('reports.show', $otherReport));
    }

    private function makeReport(User $user, State $state, Municipality $municipality, Parish $parish, Sector $sector, Activity $activity, string $placeName): Report
    {
        return Report::create([
            'user_id' => $user->id,
            'report_date' => today(),
            'reporter_first_name' => $user->name,
            'reporter_last_name' => 'Registro',
            'reporter_email' => $user->email,
            'organization' => 'ASONACOP',
            'state_id' => $state->id,
            'municipality_id' => $municipality->id,
            'parish_id' => $parish->id,
            'installation_type' => 'Comunidad / Espacio Comunitario',
            'place_name' => $placeName,
            'sector_id' => $sector->id,
            'activity_id' => $activity->id,
            'recurrence_status' => 'no_recurrente',
            'total_beneficiaries' => 1,
            'beneficiary_breakdown' => [],
        ]);
    }
}
