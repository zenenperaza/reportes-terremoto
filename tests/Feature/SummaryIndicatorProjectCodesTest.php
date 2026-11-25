<?php

namespace Tests\Feature;

use App\Models\{Donante, Indicador, IndicadorProyecto, Proyecto, Sector, SectorProyecto, SummaryIndicator, User};
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Schema};
use Tests\TestCase;

class SummaryIndicatorProjectCodesTest extends TestCase
{
    use RefreshDatabase;

    private Proyecto $firstProject;
    private Proyecto $secondProject;
    private SummaryIndicator $first;
    private SummaryIndicator $second;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $donor = Donante::create(['nombre' => 'Donante', 'estatus' => true]);
        $this->firstProject = Proyecto::create(['donante_id' => $donor->id, 'codigo' => 'PROJECT-A', 'descripcion' => 'Proyecto A', 'estatus' => true]);
        $this->secondProject = Proyecto::create(['donante_id' => $donor->id, 'codigo' => 'PROJECT-B', 'descripcion' => 'Proyecto B', 'estatus' => true]);
        $sector = Sector::create(['name' => 'Sector', 'slug' => 'summary-project-codes']);
        $indicator = Indicador::create(['codigo' => 'IND-1', 'descripcion' => 'Indicador', 'unidad_conteo' => 'Personas', 'espacio_coordinacion' => 'NNA', 'edad_desde' => 0, 'edad_hasta' => 120]);
        foreach ([$this->firstProject, $this->secondProject] as $i => $project) {
            $sectorAssignment = SectorProyecto::create(['proyecto_id' => $project->id, 'sector_id' => $sector->id]);
            $assignment = IndicadorProyecto::create(['proyecto_id' => $project->id, 'sector_proyecto_id' => $sectorAssignment->id, 'indicador_id' => $indicator->id, 'estatus' => true]);
            $summary = SummaryIndicator::create(['proyecto_id' => $project->id, 'code' => $i === 0 ? 'GCLPR/SCA28E/IC1/IE3' : 'OTHER', 'name' => 'Resumen '.$i, 'is_active' => true]);
            $summary->assignments()->attach($assignment);
            if ($i === 0) $this->first = $summary; else $this->second = $summary;
        }
    }

    private function payload(Proyecto $project, string $code): array
    {
        return ['proyecto_id' => $project->id, 'code' => $code, 'name' => 'Resumen de prueba', 'is_active' => 1];
    }

    private function migration()
    {
        return require database_path('migrations/2026_11_24_120000_scope_summary_indicator_codes_to_projects.php');
    }

    public function test_the_same_code_can_be_created_in_another_project(): void
    {
        $this->post(route('summary-indicators.store'), $this->payload($this->secondProject, $this->first->code))
            ->assertSessionHasNoErrors()->assertRedirect(route('summary-indicators.index'));
        $this->assertDatabaseHas('summary_indicators', ['proyecto_id' => $this->firstProject->id, 'code' => $this->first->code]);
        $this->assertDatabaseHas('summary_indicators', ['proyecto_id' => $this->secondProject->id, 'code' => $this->first->code]);
        $this->assertSame(2, SummaryIndicator::where('code', $this->first->code)->count());
        $this->assertDatabaseCount('summary_indicator_assignments', 2);
    }

    public function test_duplicate_code_within_the_same_project_is_rejected_with_a_clear_spanish_message(): void
    {
        $this->postJson(route('summary-indicators.store'), $this->payload($this->firstProject, '  '.$this->first->code.'  '))
            ->assertUnprocessable()->assertJsonValidationErrors('code')
            ->assertJsonPath('errors.code.0', 'Ya existe un Indicador Resumen con este código en el proyecto seleccionado.');
        $this->assertDatabaseCount('summary_indicators', 2);
        $this->get(route('summary-indicators.create'))->assertOk()->assertSee('Puede utilizar el mismo código en otros proyectos.');
    }

    public function test_editing_ignores_only_the_current_summary_and_allows_the_code_in_other_projects(): void
    {
        $this->put(route('summary-indicators.update', $this->first), $this->payload($this->firstProject, $this->first->code))->assertSessionHasNoErrors()->assertRedirect();
        $this->put(route('summary-indicators.update', $this->second), $this->payload($this->secondProject, $this->first->code))->assertSessionHasNoErrors()->assertRedirect();
        $another = SummaryIndicator::create(['proyecto_id' => $this->firstProject->id, 'code' => 'ANOTHER', 'name' => 'Otro', 'is_active' => true]);
        $this->putJson(route('summary-indicators.update', $another), $this->payload($this->firstProject, $this->first->code) + ['id' => $this->first->id])
            ->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->assertSame('ANOTHER', $another->fresh()->code);
    }

    public function test_moving_to_a_project_with_a_conflicting_code_never_detaches_existing_memberships(): void
    {
        $this->second->update(['code' => $this->first->code]);
        $members = $this->first->assignments()->pluck('indicador_proyecto.id')->all();
        $this->putJson(route('summary-indicators.update', $this->first), $this->payload($this->secondProject, $this->first->code) + ['reset_assignments_on_project_change' => 1])
            ->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->assertSame($this->firstProject->id, $this->first->fresh()->proyecto_id);
        $this->assertSame($members, $this->first->assignments()->pluck('indicador_proyecto.id')->all());
    }

    public function test_the_database_also_prevents_same_project_duplicates(): void
    {
        $this->assertTrue(Schema::hasIndex('summary_indicators', 'summary_indicators_project_code_unique', 'unique'));
        $this->assertFalse(Schema::hasIndex('summary_indicators', 'summary_indicators_code_unique'));
        try {
            SummaryIndicator::create(['proyecto_id' => $this->firstProject->id, 'code' => $this->first->code, 'name' => 'Duplicado', 'is_active' => true]);
            $this->fail('The database must enforce uniqueness within a project.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('UNIQUE', $exception->getMessage());
        }
        $this->assertDatabaseCount('summary_indicators', 2);
    }

    public function test_migration_only_changes_indexes_and_preserves_ids_memberships_and_foreign_keys(): void
    {
        $summaries = DB::table('summary_indicators')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
        $memberships = DB::table('summary_indicator_assignments')->orderBy('summary_indicator_id')->get()->map(fn ($row) => (array) $row)->all();
        $foreignKeys = Schema::getForeignKeys('summary_indicators');
        $migration = $this->migration();
        $migration->down();
        $this->assertTrue(Schema::hasIndex('summary_indicators', 'summary_indicators_code_unique', 'unique'));
        $migration->up();
        $migration->up();
        $this->assertSame($summaries, DB::table('summary_indicators')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all());
        $this->assertSame($memberships, DB::table('summary_indicator_assignments')->orderBy('summary_indicator_id')->get()->map(fn ($row) => (array) $row)->all());
        $this->assertSame($foreignKeys, Schema::getForeignKeys('summary_indicators'));
        $this->assertTrue(Schema::hasIndex('summary_indicators', 'summary_indicators_project_code_unique', 'unique'));
        $this->assertFalse(Schema::hasIndex('summary_indicators', 'summary_indicators_code_unique'));
    }

    public function test_rollback_stops_without_modifying_data_if_codes_are_already_reused(): void
    {
        $this->second->update(['code' => $this->first->code]);
        $before = DB::table('summary_indicators')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
        try {
            $this->migration()->down();
            $this->fail('Rollback must not remove or rename summaries to restore global uniqueness.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('No se puede restaurar la unicidad global', $exception->getMessage());
        }
        $this->assertSame($before, DB::table('summary_indicators')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all());
        $this->assertDatabaseCount('summary_indicator_assignments', 2);
        $this->assertTrue(Schema::hasIndex('summary_indicators', 'summary_indicators_project_code_unique', 'unique'));
    }

    public function test_invalid_projects_and_non_admin_access_are_still_rejected(): void
    {
        foreach ([null, 999999, ['invalid']] as $projectId) {
            $this->postJson(route('summary-indicators.store'), array_replace($this->payload($this->firstProject, 'NEW'), ['proyecto_id' => $projectId]))
                ->assertUnprocessable()->assertJsonValidationErrors('proyecto_id');
        }
        $this->actingAs(User::factory()->create(['role' => 'reporter']));
        $this->postJson(route('summary-indicators.store'), $this->payload($this->secondProject, $this->first->code))->assertForbidden();
    }
}
