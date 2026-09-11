<?php

namespace Tests\Feature;

use App\Models\Indicador;
use App\Models\IndicatorGroup;
use Database\Seeders\IndicatorGroupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndicatorGroupSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_the_six_groups_and_assigns_matching_indicators(): void
    {
        $codes = [
            'GCLPR/SCA12/IC1/IE1',
            'GCLPR/SCA10/IC1/IE2',
            'GCLPR/SCA17/IC1/IE2',
            'GCLPR/SCA18/IC1/IE2',
            'GCLPR/SCA13/IC1/IE1',
            'GCLPR/SCA24/IC1/IE3',
        ];

        foreach ($codes as $code) {
            Indicador::create([
                'codigo' => $code,
                'descripcion' => 'Indicador de prueba '.$code,
                'unidad_conteo' => 'Personas',
                'espacio_coordinacion' => 'NNA',
                'edad_desde' => 0,
                'edad_hasta' => 17,
            ]);
        }

        $this->seed(IndicatorGroupSeeder::class);
        $this->seed(IndicatorGroupSeeder::class);

        $this->assertDatabaseCount('indicator_groups', 6);
        $this->assertSame(
            [1, 2, 3, 4, 5, 6],
            IndicatorGroup::query()->orderBy('sort_order')->pluck('sort_order')->all(),
        );

        foreach ($codes as $code) {
            $indicator = Indicador::where('codigo', $code)->firstOrFail();
            $this->assertNotNull($indicator->indicator_group_id);
            $this->assertNotNull($indicator->nombre_corto);
        }

        $this->assertDatabaseHas('indicator_groups', [
            'name' => 'Apoyo psicosocial (SMAPS)',
            'sort_order' => 1,
        ]);
        $this->assertDatabaseHas('indicator_groups', [
            'name' => 'Protección comunitaria e información',
            'sort_order' => 6,
        ]);
    }

    public function test_it_does_not_replace_an_existing_manual_assignment(): void
    {
        $manualGroup = IndicatorGroup::create([
            'name' => 'Clasificación manual',
            'description' => 'Grupo elegido por el administrador.',
            'sort_order' => 50,
        ]);
        $indicator = Indicador::create([
            'indicator_group_id' => $manualGroup->id,
            'codigo' => 'GCLPR/SCA12/IC1/IE1',
            'nombre_corto' => 'Nombre corto personalizado',
            'descripcion' => 'Indicador clasificado manualmente',
            'unidad_conteo' => 'Personas',
            'espacio_coordinacion' => 'NNA',
            'edad_desde' => 0,
            'edad_hasta' => 17,
        ]);

        $this->seed(IndicatorGroupSeeder::class);

        $this->assertSame($manualGroup->id, $indicator->fresh()->indicator_group_id);
        $this->assertSame('Nombre corto personalizado', $indicator->fresh()->nombre_corto);
    }
}
