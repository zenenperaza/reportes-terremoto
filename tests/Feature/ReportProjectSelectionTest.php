<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Donante;
use App\Models\Municipality;
use App\Models\Parish;
use App\Models\PlaceName;
use App\Models\Proyecto;
use App\Models\Report;
use App\Models\Sector;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReportProjectSelectionTest extends TestCase
{
    use RefreshDatabase;

    private int $sequence = 0;

    private function project(bool $active = true): Proyecto
    {
        $donor = Donante::firstOrCreate(['nombre' => 'Donante de prueba'], ['estatus' => true]);
        $number = ++$this->sequence;

        return Proyecto::create(['donante_id' => $donor->id, 'codigo' => 'PROY-'.$number,
            'descripcion' => 'Proyecto '.$number, 'estatus' => $active]);
    }

    private function projectSelect(TestResponse $response): \DOMXPath
    {
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        $xpath = new \DOMXPath($dom);
        $this->assertSame(1, $xpath->query('//select[@id="proyecto_id" and @name="proyecto_id" and @required]')->length);

        return $xpath;
    }

    public static function roles(): array
    {
        return [['reporter'], ['coordinator']];
    }

    #[DataProvider('roles')]
    public function test_one_assigned_active_project_is_selected_without_exposing_other_projects(string $role): void
    {
        $user = User::factory()->create(['role' => $role]);
        $project = $this->project();
        $other = $this->project();
        $inactive = $this->project(false);
        $user->projects()->attach([$project->id, $inactive->id]);
        $response = $this->actingAs($user)->get(route('reports.create'))->assertOk()
            ->assertViewHas('selectedProjectId', $project->id);
        $xpath = $this->projectSelect($response);
        $this->assertSame((string) $project->id, $xpath->evaluate('string(//select[@id="proyecto_id"]/option[@selected]/@value)'));
        $this->assertSame(0, $xpath->query('//select[@id="proyecto_id"]/option[@value="'.$other->id.'" or @value="'.$inactive->id.'"]')->length);
    }

    #[DataProvider('roles')]
    public function test_two_or_more_assigned_projects_require_explicit_selection(string $role): void
    {
        $user = User::factory()->create(['role' => $role]);
        $user->projects()->attach([$this->project()->id, $this->project()->id]);
        $this->actingAs($user);
        foreach ([2, 3] as $count) {
            if ($count === 3) {
                $user->projects()->attach($this->project()->id);
            }
            $response = $this->get(route('reports.create'))->assertOk()->assertViewHas('selectedProjectId', null);
            $xpath = $this->projectSelect($response);
            $this->assertSame($count, $xpath->query('//select[@id="proyecto_id"]/option[@value!=""]')->length);
            $this->assertSame(1, $xpath->query('//select[@id="proyecto_id"]/option[@value="" and @selected]')->length);
            $this->assertSame(0, $xpath->query('//select[@id="proyecto_id"]/option[@value!="" and @selected]')->length);
        }
    }

    public function test_admin_selects_only_a_single_available_project_and_does_not_default_to_the_first_of_many(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $first = $this->project();
        $this->get(route('reports.create'))->assertOk()->assertViewHas('selectedProjectId', $first->id);
        $this->project();
        $response = $this->get(route('reports.create'))->assertOk()->assertViewHas('selectedProjectId', null);
        $this->assertSame(0, $this->projectSelect($response)->query('//select[@id="proyecto_id"]/option[@value!="" and @selected]')->length);
    }

    public function test_no_assigned_projects_leaves_the_required_project_select_empty(): void
    {
        $user = User::factory()->create(['role' => 'reporter']);
        $this->project();
        $response = $this->actingAs($user)->get(route('reports.create'))->assertOk()->assertViewHas('selectedProjectId', null);
        $this->assertSame(0, $this->projectSelect($response)->query('//select[@id="proyecto_id"]/option[@value!=""]')->length);
    }

    public function test_previous_manual_selection_is_preserved_after_validation_errors(): void
    {
        $user = User::factory()->create(['role' => 'reporter']);
        $first = $this->project();
        $second = $this->project();
        $user->projects()->attach([$first->id, $second->id]);
        $response = $this->actingAs($user)->withSession(['_old_input' => ['proyecto_id' => (string) $second->id]])
            ->get(route('reports.create'))->assertOk()->assertViewHas('selectedProjectId', (string) $second->id);
        $this->assertSame((string) $second->id, $this->projectSelect($response)->evaluate('string(//select[@id="proyecto_id"]/option[@selected]/@value)'));
    }

    private function legacyPayload(): array
    {
        $state = State::create(['code' => 'A', 'name' => 'Estado']);
        $municipality = Municipality::create(['state_id' => $state->id, 'code' => 'A01', 'name' => 'Municipio']);
        $parish = Parish::create(['municipality_id' => $municipality->id, 'code' => 'A0101', 'name' => 'Parroquia']);
        $sector = Sector::create(['name' => 'Sector', 'slug' => 'sector']);
        $activity = Activity::create(['sector_id' => $sector->id, 'code' => 'OLD', 'title' => 'Actividad anterior', 'sort_order' => 1]);
        PlaceName::create(['name' => 'Lugar de prueba']);

        return ['report_date' => today()->toDateString(), 'organization' => 'ASONACOP',
            'state_id' => $state->id, 'municipality_id' => $municipality->id, 'parish_id' => $parish->id,
            'installation_type' => 'Comunidad / Espacio Comunitario', 'place_name' => 'Lugar de prueba',
            'sector_id' => $sector->id, 'activity_id' => $activity->id];
    }

    public function test_new_records_and_immediate_beneficiary_saves_require_a_project_even_if_legacy_sector_is_sent(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $payload = $this->legacyPayload();
        $person = ['has_informed_consent' => true, 'age' => 12, 'sex' => 'Mujer', 'is_recurrent' => false];
        foreach (['reports.store' => ['beneficiaries' => [$person]], 'beneficiaries.store' => ['beneficiary' => $person]] as $route => $people) {
            foreach ([[], ['proyecto_id' => '']] as $project) {
                $this->postJson(route($route), $payload + $people + $project)->assertUnprocessable()
                    ->assertJsonValidationErrors('proyecto_id')
                    ->assertJsonPath('errors.proyecto_id.0', 'Seleccione un proyecto para registrar la actividad.');
            }
        }
        $this->assertDatabaseCount('reports', 0);
        $this->assertDatabaseCount('beneficiaries', 0);
    }

    public function test_editing_an_existing_legacy_record_remains_supported(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);
        $payload = $this->legacyPayload();
        $report = Report::create($payload + ['user_id' => $admin->id, 'reporting_period' => '2026-10',
            'reporter_first_name' => 'Original', 'reporter_last_name' => 'Prueba', 'reporter_email' => $admin->email,
            'total_beneficiaries' => 1, 'beneficiary_breakdown' => [], 'recurrence_status' => 'nuevo']);
        $report->beneficiaries()->create(['has_informed_consent' => true, 'age' => 12, 'sex' => 'Mujer', 'is_recurrent' => false]);
        $this->putJson(route('reports.update', $report), $payload)->assertOk();
        $this->assertNull($report->refresh()->proyecto_id);
    }
}
