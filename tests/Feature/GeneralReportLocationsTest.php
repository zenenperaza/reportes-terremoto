<?php

namespace Tests\Feature;

use App\Models\Municipality;
use App\Models\Parish;
use App\Models\Report;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GeneralReportLocationsTest extends TestCase
{
    use RefreshDatabase;

    private array $locations;

    protected function setUp(): void
    {
        parent::setUp();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);
        foreach (['A', 'B', 'C'] as $code) {
            $state = State::create(['code' => $code, 'name' => 'Estado '.$code]);
            $municipality = Municipality::create(['state_id' => $state->id, 'code' => $code.'01', 'name' => 'Central']);
            $parish = Parish::create(['municipality_id' => $municipality->id, 'code' => $code.'0101', 'name' => 'Centro']);
            $this->locations[$code] = compact('state', 'municipality', 'parish');
            $report = Report::create([
                'user_id' => $admin->id, 'report_date' => today(), 'reporter_first_name' => 'Prueba',
                'reporter_last_name' => 'Registro', 'reporter_email' => $admin->email, 'organization' => 'ASONACOP',
                'state_id' => $state->id, 'municipality_id' => $municipality->id, 'parish_id' => $parish->id,
                'installation_type' => 'Comunidad / Espacio Comunitario', 'place_name' => 'Lugar '.$code,
                'recurrence_status' => 'nuevo', 'total_beneficiaries' => 1, 'beneficiary_breakdown' => [],
            ]);
            $report->beneficiaries()->create(['full_name' => 'Persona de prueba', 'age' => 10, 'sex' => 'Mujer', 'is_recurrent' => false]);
        }
    }

    public function test_multiple_states_filter_all_results_and_remain_selected(): void
    {
        $ids = [$this->locations['A']['state']->id, $this->locations['B']['state']->id];
        $response = $this->get(route('general-reports.index', ['state_id' => $ids]))->assertOk()
            ->assertSee('name="state_id[]"', false)->assertSee('multiple aria-describedby=', false)
            ->assertViewHas('summary', fn ($summary) => $summary['beneficiaries'] === 2 && $summary['attentions'] === 2)
            ->assertViewHas('filters', fn ($filters) => $filters['state_id'] === $ids)
            ->assertViewHas('municipalities', fn ($items) => $items->count() === 2)
            ->assertViewHas('parishes', fn ($items) => $items->count() === 2);
        foreach ($ids as $id) {
            $response->assertSee('value="'.$id.'" selected', false);
        }
        $this->assertEqualsCanonicalizing(['Estado A', 'Estado B'], $response->viewData('charts')['states']['labels']->all());
        $this->assertSame(2, $response->viewData('charts')['sex']['values'][1]);
    }

    public function test_cascade_returns_union_of_states_and_parishes_can_be_narrowed_by_municipality(): void
    {
        $a = $this->locations['A'];
        $b = $this->locations['B'];
        $filters = ['state_id' => [$a['state']->id, $b['state']->id]];
        $response = $this->getJson(route('general-reports.locations', $filters))->assertOk()
            ->assertJsonCount(2, 'municipalities')->assertJsonCount(2, 'parishes');
        $this->assertEqualsCanonicalizing(['Central — Estado A', 'Central — Estado B'], array_column($response->json('municipalities'), 'name'));
        $this->getJson(route('general-reports.locations', $filters + ['municipality_id' => $b['municipality']->id]))->assertOk()
            ->assertJsonCount(2, 'municipalities')->assertJsonCount(1, 'parishes')
            ->assertJsonPath('parishes.0.id', $b['parish']->id);
        $this->get(route('general-reports.index', $filters + ['municipality_id' => $b['municipality']->id, 'parish_id' => $b['parish']->id]))
            ->assertOk()->assertViewHas('summary', fn ($summary) => $summary['beneficiaries'] === 1);
        $this->get(route('general-reports.index', $filters + ['parish_id' => $a['parish']->id]))
            ->assertOk()->assertViewHas('summary', fn ($summary) => $summary['beneficiaries'] === 1);
    }

    public function test_no_states_means_all_and_legacy_single_state_urls_still_work(): void
    {
        foreach ([[], ['state_id' => ''], ['state_id' => []]] as $filters) {
            $this->get(route('general-reports.index', $filters))->assertOk()
                ->assertViewHas('summary', fn ($summary) => $summary['beneficiaries'] === 3);
            $this->getJson(route('general-reports.locations', $filters))->assertOk()
                ->assertJsonCount(3, 'municipalities')->assertJsonCount(3, 'parishes');
        }
        $this->get(route('general-reports.index', ['state_id' => $this->locations['A']['state']->id]))->assertOk()
            ->assertViewHas('summary', fn ($summary) => $summary['beneficiaries'] === 1);
    }

    public function test_invalid_states_and_cross_state_locations_are_rejected(): void
    {
        $a = $this->locations['A'];
        $b = $this->locations['B'];
        $invalid = [
            [['state_id' => ['invalid']], 'state_id.0'],
            [['state_id' => [999999]], 'state_id.0'],
            [['state_id' => [[$a['state']->id]]], 'state_id.0'],
            [['state_id' => [$a['state']->id, $a['state']->id]], 'state_id.0'],
            [['state_id' => [$a['state']->id], 'municipality_id' => $b['municipality']->id], 'municipality_id'],
            [['state_id' => [$a['state']->id], 'parish_id' => $b['parish']->id], 'parish_id'],
            [['state_id' => [$a['state']->id, $b['state']->id], 'municipality_id' => $a['municipality']->id, 'parish_id' => $b['parish']->id], 'parish_id'],
        ];
        foreach ($invalid as [$filters, $field]) {
            foreach (['general-reports.index', 'general-reports.locations'] as $route) {
                $this->getJson(route($route, $filters))->assertUnprocessable()->assertJsonValidationErrors($field);
            }
        }
    }

    public function test_state_selection_does_not_expand_report_visibility(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'reporter']));
        $this->get(route('general-reports.index', ['state_id' => [$this->locations['A']['state']->id, $this->locations['B']['state']->id]]))
            ->assertOk()->assertViewHas('summary', fn ($summary) => $summary['beneficiaries'] === 0);
    }

    public function test_location_endpoint_requires_authentication(): void
    {
        auth()->logout();
        $this->getJson(route('general-reports.locations'))->assertUnauthorized();
    }
}
