<?php

namespace Tests\Feature;

use App\Models\Donante;
use App\Models\Indicador;
use App\Models\IndicadorProyecto;
use App\Models\Proyecto;
use App\Models\User;
use Database\Seeders\IndicadorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_manage_project_indicators_from_modal(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->actingAs($admin);

        $this->post(route('donantes.store'), [
            'nombre' => 'UNICEF', 'estatus' => 1, 'enlaces' => 'https://www.unicef.org/',
        ])->assertRedirect(route('donantes.index'));
        $donante = Donante::firstOrFail();

        $this->post(route('proyectos.store'), [
            'donante_id' => $donante->id, 'estatus' => 1, 'codigo' => 'PROY-001',
            'descripcion' => 'Respuesta al terremoto', 'inicio' => '2026-08-01', 'fin' => '2026-12-31',
        ])->assertRedirect(route('proyectos.index'));
        $proyecto = Proyecto::firstOrFail();

        $indicador = Indicador::create([
            'codigo' => 'GCLPR/SCA10/IC1/IE2',
            'descripcion' => 'Número de nuevos casos de gestión de protección.',
            'unidad_conteo' => 'Personas', 'espacio_coordinacion' => 'NNA', 'edad_desde' => 0, 'edad_hasta' => 17,
        ]);
        $segundoIndicador = Indicador::create([
            'codigo' => 'GCLPR/SCA12/IC1/IE1',
            'descripcion' => 'Número de niñas, niños y adolescentes que reciben apoyo psicosocial mediante actividades grupales.',
            'unidad_conteo' => 'Personas', 'espacio_coordinacion' => 'NNA', 'edad_desde' => 0, 'edad_hasta' => 17,
        ]);

        $this->get(route('proyectos.indicadores.index', $proyecto))
            ->assertOk()->assertSee('PROY-001')->assertSee($segundoIndicador->descripcion)
            ->assertSee('modalAgregarIndicador');

        $this->post(route('proyectos.indicadores.store', $proyecto), [
            'indicador_id' => $indicador->id,
            'meta_cuantitativa' => 150,
            'meta_cualitativa' => 'Atención adaptada a las necesidades.',
        ])->assertRedirect(route('proyectos.indicadores.index', $proyecto));

        $this->post(route('proyectos.indicadores.store', $proyecto), [
            'indicador_id' => $segundoIndicador->id,
            'meta_cuantitativa' => 275,
            'meta_cualitativa' => 'Actividades grupales.',
        ])->assertRedirect(route('proyectos.indicadores.index', $proyecto));

        $this->assertSame(2, IndicadorProyecto::count());
        $asignacion = IndicadorProyecto::where('indicador_id', $indicador->id)->firstOrFail();
        $this->assertSame(150, $asignacion->meta_cuantitativa);
        $this->assertDatabaseHas('indicador_proyecto', [
            'proyecto_id' => $proyecto->id, 'indicador_id' => $segundoIndicador->id,
            'meta_cuantitativa' => 275, 'meta_cualitativa' => 'Actividades grupales.',
        ]);

        $this->get(route('proyectos.index'))->assertOk()->assertSee('Gestionar (2)');
        $this->get(route('proyectos.indicadores.index', $proyecto))->assertOk()->assertSee('150');
        $this->get(route('users.create'))->assertOk()
            ->assertSee('select2.full.min.js', false)
            ->assertSee("$('#assigned-project-ids').select2", false);
        $this->get(route('reports.create'))->assertOk()
            ->assertSee('name="proyecto_id"', false)
            ->assertSee('name="indicador_proyecto_id"', false)
            ->assertSee('PROY-001');
        $this->get(route('indicador-proyecto.edit', $asignacion))->assertOk();
    }

    public function test_non_administrator_cannot_access_catalog_management(): void
    {
        $reporter = User::factory()->create(['role' => 'reporter', 'is_active' => true]);
        $donante = Donante::create(['nombre' => 'Donante privado', 'estatus' => true]);
        $proyecto = Proyecto::create([
            'donante_id' => $donante->id, 'estatus' => true,
            'codigo' => 'PRIV-001', 'descripcion' => 'Proyecto privado',
        ]);

        $this->actingAs($reporter)->get(route('donantes.index'))->assertForbidden();
        $this->actingAs($reporter)->get(route('proyectos.index'))->assertForbidden();
        $this->actingAs($reporter)->get(route('indicadores.index'))->assertForbidden();
        $this->actingAs($reporter)->get(route('proyectos.indicadores.index', $proyecto))->assertForbidden();
    }

    public function test_indicator_uses_a_valid_age_range(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->actingAs($admin)->get(route('indicadores.create'))
            ->assertOk()
            ->assertSee('name="unidad_conteo"', false)
            ->assertSee('Productos / Informes / Análisis')
            ->assertSee('Comités o mecanismos comunitarios')
            ->assertSee('Actividades de incidencia');
        $data = [
            'codigo' => 'IND-EDAD-01',
            'descripcion' => 'Indicador para personas adultas',
            'unidad_conteo' => 'Personas',
            'espacio_coordinacion' => 'NNA',
            'edad_desde' => 20,
            'edad_hasta' => 49,
        ];

        $this->actingAs($admin)->post(route('indicadores.store'), $data)
            ->assertRedirect(route('indicadores.index'));
        $this->assertDatabaseHas('indicadores', [
            'codigo' => 'IND-EDAD-01', 'edad_desde' => 20, 'edad_hasta' => 49,
        ]);

        $this->actingAs($admin)->post(route('indicadores.store'), array_replace($data, [
            'codigo' => 'IND-EDAD-INVALIDO', 'edad_desde' => 50, 'edad_hasta' => 20,
        ]))->assertSessionHasErrors('edad_hasta');
        $this->assertDatabaseMissing('indicadores', ['codigo' => 'IND-EDAD-INVALIDO']);

        $this->actingAs($admin)->post(route('indicadores.store'), array_replace($data, [
            'codigo' => 'IND-UNIDAD-INVALIDA', 'unidad_conteo' => 'Otra unidad',
        ]))->assertSessionHasErrors('unidad_conteo');
    }

    public function test_indicator_pagination_uses_bootstrap_controls(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->seed(IndicadorSeeder::class);

        $this->actingAs($admin)->get(route('indicadores.index'))->assertOk()
            ->assertSee('page-link', false)->assertDontSee('<svg', false);
    }
}
