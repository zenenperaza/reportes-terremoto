<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Municipality;
use App\Models\Parish;
use App\Models\Sector;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login_and_can_see_login_form(): void
    {
        $this->get('/')->assertRedirect('/panel');
        $this->get('/ingresar')->assertOk()->assertSee('Ingrese a su cuenta');
    }

    public function test_authenticated_user_can_submit_a_report_with_consistent_breakdown(): void
    {
        $user = User::factory()->create();
        $state = State::create(['code' => 'VE01', 'name' => 'Distrito Capital']);
        $municipality = Municipality::create(['state_id' => $state->id, 'code' => 'VE0101', 'name' => 'Libertador']);
        $parish = Parish::create(['municipality_id' => $municipality->id, 'code' => 'VE010101', 'name' => 'Altagracia']);
        $sector = Sector::create(['name' => 'Protección de la niñez', 'slug' => 'proteccion-ninez', 'sort_order' => 1]);
        $activity = Activity::create(['sector_id' => $sector->id, 'code' => 'TEST-01', 'title' => 'Actividad de prueba', 'sort_order' => 1]);

        $this->actingAs($user)->get('/reportes/nuevo')->assertOk()->assertSee('Registrar actividad');

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
            'recurrence_status' => 'no_recurrente',
            'total_beneficiaries' => 8,
            'beneficiary_scheme' => 'tradicional',
            'beneficiary_breakdown' => [
                'girls_0_5' => 2, 'boys_0_5' => 1, 'girls_6_11' => 1, 'boys_6_11' => 1,
                'girls_12_17' => 1, 'boys_12_17' => 0, 'women_18_59' => 1, 'men_18_59' => 1,
                'women_60_plus' => 0, 'men_60_plus' => 0,
            ],
            'people_with_disabilities' => 1,
            'indigenous_people' => 0,
            'pregnant_or_lactating_women' => 1,
            'qualitative_notes' => 'Actividad realizada sin novedades.',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('reports', ['place_name' => 'Comunidad El Carmen', 'total_beneficiaries' => 8]);
        $this->get($response->headers->get('Location'))->assertOk()->assertSee('Actividad de prueba');
        $this->get('/panel')->assertOk()->assertSee('Mis actividades reportadas');
    }
}
