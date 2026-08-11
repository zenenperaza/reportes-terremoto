<?php

namespace Tests\Feature;

use Database\Seeders\ActividadSeeder;
use Database\Seeders\ServicioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActividadServicioSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeders_populate_activities_and_services_without_duplicates(): void
    {
        $this->seed([ActividadSeeder::class, ServicioSeeder::class]);
        $this->seed([ActividadSeeder::class, ServicioSeeder::class]);

        $this->assertDatabaseCount('actividades', 9);
        $this->assertDatabaseCount('servicios', 14);
        $this->assertDatabaseHas('actividades', [
            'codigo' => 'ACT-1.1',
            'descripcion' => 'Gestión y acompañamiento especializado para la restitución del derecho a la identidad.',
        ]);
        $this->assertDatabaseHas('actividades', [
            'codigo' => 'ACT-4.1',
            'descripcion' => 'Recolección de opiniones, quejas y preguntas mediante mecanismos de retroalimentación establecidos.',
        ]);
        $this->assertDatabaseHas('servicios', [
            'nombre' => 'KITS DE ALIMENTACIÓN (ASONACOP)',
            'descripcion' => 'KITS DE ALIMENTACIÓN (ASONACOP)',
        ]);
    }
}
