<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Municipality;
use App\Models\Parish;
use App\Models\PlaceName;
use App\Models\Report;
use App\Models\Sector;
use App\Models\State;
use App\Models\User;
use App\Services\ReportRegistrant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportRegistrantReadOnlyTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_form_uses_readonly_account_fields_not_forged_old_input(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'name' => 'Barbara Cisneros']);
        $response = $this->actingAs($user)->withSession(['_old_input' => $this->forgedIdentity()])
            ->get(route('reports.create'))->assertOk();
        $this->assertReadOnlyFields($response->getContent(), ReportRegistrant::fields($user));
    }

    public function test_bulk_and_immediate_creation_ignore_forged_identity_and_owner(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'name' => 'Barbara Cisneros']);
        $payload = $this->payload() + $this->forgedIdentity() + ['user_id' => 99999];
        $beneficiary = $this->beneficiary();
        $this->actingAs($user)->post(route('reports.store'), $payload + ['beneficiaries' => [$beneficiary]])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->postJson(route('beneficiaries.store'), $payload + ['beneficiary' => $beneficiary])->assertCreated();
        $this->assertDatabaseCount('reports', 2);
        foreach (Report::all() as $report) {
            $this->assertSame(ReportRegistrant::fields($user), $report->only(array_keys($this->forgedIdentity())));
            $this->assertSame($user->id, $report->user_id);
        }
    }

    public function test_administrator_editing_or_adding_to_existing_report_preserves_original_identity(): void
    {
        $owner = User::factory()->create(['role' => 'reporter', 'name' => 'Barbara Cisneros']);
        $payload = $this->payload();
        $first = $this->actingAs($owner)->postJson(route('beneficiaries.store'), $payload + ['beneficiary' => $this->beneficiary()])
            ->assertCreated();
        $report = Report::findOrFail($first->json('report.id'));
        $snapshot = ReportRegistrant::fields($owner, $report);
        $admin = User::factory()->create(['role' => 'admin', 'name' => 'Administrador Sistema']);
        $response = $this->actingAs($admin)->get(route('reports.edit', $report))->assertOk();
        $this->assertReadOnlyFields($response->getContent(), $snapshot);
        $this->putJson(route('reports.update', $report), $payload + $this->forgedIdentity() + ['activity_details' => 'Dato editable'])
            ->assertOk();
        $this->assertSame($snapshot, ReportRegistrant::fields($admin, $report->fresh()));
        $this->postJson(route('beneficiaries.store'), $payload + $this->forgedIdentity() + [
            'report_id' => $report->id, 'beneficiary' => $this->beneficiary(),
        ])->assertOk();
        $this->assertDatabaseCount('reports', 1);
        $this->assertSame(2, $report->beneficiaries()->count());
        $this->assertSame($snapshot, ReportRegistrant::fields($admin, $report->fresh()));
        $this->assertSame($owner->id, $report->fresh()->user_id);
    }

    public function test_user_with_single_name_can_register_without_inventing_a_last_name(): void
    {
        $user = User::factory()->create(['role' => 'reporter', 'name' => 'ASONACOP']);
        $this->actingAs($user)->postJson(route('beneficiaries.store'), $this->payload() + ['beneficiary' => $this->beneficiary()])
            ->assertCreated();
        $this->assertDatabaseHas('reports', ['user_id' => $user->id, 'reporter_first_name' => 'ASONACOP', 'reporter_last_name' => '', 'reporter_email' => $user->email]);
    }

    private function assertReadOnlyFields(string $html, array $expected): void
    {
        $document = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new \DOMXPath($document);
        foreach ($expected as $name => $value) {
            $inputs = $xpath->query('//input[@name="'.$name.'"]');
            $this->assertSame(1, $inputs->length);
            $this->assertTrue($inputs->item(0)->hasAttribute('readonly'));
            $this->assertSame($value, $inputs->item(0)->getAttribute('value'));
        }
    }

    private function forgedIdentity(): array
    {
        return ['reporter_first_name' => 'Otra', 'reporter_last_name' => 'Persona', 'reporter_email' => 'otra@example.test'];
    }

    private function beneficiary(): array
    {
        return ['has_informed_consent' => true, 'full_name' => 'Persona de prueba', 'age' => 10, 'sex' => 'Mujer',
            'disability' => 'Ninguna', 'ethnicity' => 'Ninguna', 'pregnant_lactating' => 'N/A', 'is_recurrent' => false];
    }

    private function payload(): array
    {
        $state = State::create(['code' => 'VE01', 'name' => 'Estado']);
        $municipality = Municipality::create(['state_id' => $state->id, 'code' => 'VE0101', 'name' => 'Municipio']);
        $parish = Parish::create(['municipality_id' => $municipality->id, 'code' => 'VE010101', 'name' => 'Parroquia']);
        $sector = Sector::create(['name' => 'Sector', 'slug' => 'sector', 'sort_order' => 1]);
        $activity = Activity::create(['sector_id' => $sector->id, 'code' => 'ACT-01', 'title' => 'Actividad', 'sort_order' => 1]);
        PlaceName::create(['name' => 'Lugar de prueba']);

        return ['report_date' => today()->toDateString(), 'organization' => 'ASONACOP', 'state_id' => $state->id,
            'municipality_id' => $municipality->id, 'parish_id' => $parish->id, 'installation_type' => 'Comunidad / Espacio Comunitario',
            'place_name' => 'Lugar de prueba', 'sector_id' => $sector->id, 'activity_id' => $activity->id];
    }
}
