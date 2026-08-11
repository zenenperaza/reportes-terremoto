<?php

namespace Tests\Feature;

use App\Models\Actividad;
use App\Models\ActividadIndicador;
use App\Models\Donante;
use App\Models\Indicador;
use App\Models\IndicadorProyecto;
use App\Models\Proyecto;
use App\Models\Servicio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProjectActivityServiceHierarchyTest extends TestCase
{
    use RefreshDatabase;

    public function test_hierarchy_tables_and_unique_assignments_are_available(): void
    {
        $this->assertTrue(Schema::hasColumns('actividades', ['id', 'codigo', 'descripcion']));
        $this->assertTrue(Schema::hasColumns('servicios', ['id', 'nombre', 'descripcion']));
        $this->assertTrue(Schema::hasColumns('actividad_indicador', ['id', 'indicador_proyecto_id', 'actividad_id', 'estatus', 'meta']));
        $this->assertTrue(Schema::hasColumns('servicio_actividad', ['id', 'actividad_indicador_id', 'servicio_id', 'estatus', 'cantidad_disponible']));

        [$indicadorProyecto, $actividad, $servicio] = $this->catalogs();
        $actividadIndicador = ActividadIndicador::create([
            'indicador_proyecto_id' => $indicadorProyecto->id,
            'actividad_id' => $actividad->id,
            'meta' => 50,
            'estatus' => true,
        ]);
        $actividadIndicador->asignacionesServicios()->create([
            'servicio_id' => $servicio->id,
            'cantidad_disponible' => 25,
            'estatus' => true,
        ]);

        $this->assertSame('ACT-01', $indicadorProyecto->asignacionesActividades()->firstOrFail()->actividad->codigo);
        $this->assertSame('Orientación legal', $actividadIndicador->asignacionesServicios()->firstOrFail()->servicio->nombre);
    }

    public function test_administrator_can_manage_catalogs_and_project_hierarchy(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        [$indicadorProyecto, $actividad, $servicio] = $this->catalogs();

        $this->actingAs($admin)->get(route('actividades.index'))->assertOk()->assertSee('ACT-01');
        $this->actingAs($admin)->get(route('servicios.index'))->assertOk()->assertSee('Orientación legal');

        $this->actingAs($admin)->post(route('indicador-proyecto.actividades.store', $indicadorProyecto), [
            'actividad_id' => $actividad->id,
            'meta' => 100,
        ])->assertRedirect();
        $actividadIndicador = ActividadIndicador::firstOrFail();

        $this->actingAs($admin)->get(route('indicador-proyecto.actividades.index', $indicadorProyecto))
            ->assertOk()->assertSee('ACT-01')->assertSee('Gestionar servicios');
        $this->actingAs($admin)->post(route('actividad-indicador.servicios.store', $actividadIndicador), [
            'servicio_id' => $servicio->id,
            'cantidad_disponible' => 80,
        ])->assertRedirect();
        $this->actingAs($admin)->get(route('actividad-indicador.servicios.index', $actividadIndicador))
            ->assertOk()->assertSee('Orientación legal')->assertSee('80');

        $this->actingAs($admin)->get(route('proyectos.show', $indicadorProyecto->proyecto_id))
            ->assertOk()
            ->assertSee('Indicadores · Actividades · Servicios')
            ->assertSee('IND-01')
            ->assertSee('ACT-01')
            ->assertSee('Orientación legal')
            ->assertSee('Cantidad:')
            ->assertSee('80');

        $this->assertDatabaseHas('actividad_indicador', ['meta' => 100, 'estatus' => true]);
        $this->assertDatabaseHas('servicio_actividad', ['cantidad_disponible' => 80, 'estatus' => true]);
    }

    public function test_reporter_cannot_manage_new_catalogs(): void
    {
        $reporter = User::factory()->create(['role' => 'reporter']);
        $this->actingAs($reporter)->get(route('actividades.index'))->assertForbidden();
        $this->actingAs($reporter)->get(route('servicios.index'))->assertForbidden();
    }

    private function catalogs(): array
    {
        $donante = Donante::create(['nombre' => 'UNICEF', 'estatus' => true]);
        $proyecto = Proyecto::create(['donante_id' => $donante->id, 'codigo' => 'PROY-01', 'descripcion' => 'Proyecto', 'estatus' => true]);
        $indicador = Indicador::create([
            'codigo' => 'IND-01', 'descripcion' => 'Indicador de prueba', 'unidad_conteo' => 'Personas',
            'espacio_coordinacion' => 'NNA', 'edad_desde' => 0, 'edad_hasta' => 17,
        ]);
        $indicadorProyecto = IndicadorProyecto::create([
            'proyecto_id' => $proyecto->id, 'indicador_id' => $indicador->id, 'estatus' => true,
        ]);
        $actividad = Actividad::create(['codigo' => 'ACT-01', 'descripcion' => 'Asesoría individual']);
        $servicio = Servicio::create(['nombre' => 'Orientación legal', 'descripcion' => 'Atención especializada']);

        return [$indicadorProyecto, $actividad, $servicio];
    }
}
