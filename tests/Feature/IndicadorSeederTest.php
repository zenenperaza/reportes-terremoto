<?php

namespace Tests\Feature;

use App\Models\Indicador;
use Database\Seeders\IndicadorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndicadorSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_imports_the_22_indicators_without_duplicates(): void
    {
        $this->seed(IndicadorSeeder::class);

        $this->assertSame(22, Indicador::count());
        $this->assertDatabaseHas('indicadores', [
            'codigo' => 'GCLPR/SCA10/IC1/IE2',
            'unidad_conteo' => 'Personas',
            'espacio_coordinacion' => 'NNA',
            'edad_desde' => 0,
            'edad_hasta' => 17,
        ]);

        $this->seed(IndicadorSeeder::class);

        $this->assertSame(22, Indicador::count());
    }
}
