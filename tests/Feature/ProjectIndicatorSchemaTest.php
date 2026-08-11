<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProjectIndicatorSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_indicator_schema_is_created_with_expected_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('donantes', [
            'id', 'nombre', 'estatus', 'enlaces', 'created_at', 'updated_at',
        ]));
        $this->assertTrue(Schema::hasColumns('proyectos', [
            'id', 'donante_id', 'estatus', 'codigo', 'descripcion', 'inicio', 'fin', 'created_at', 'updated_at',
        ]));
        $this->assertTrue(Schema::hasColumns('indicadores', [
            'id', 'codigo', 'descripcion', 'unidad_conteo', 'espacio_coordinacion', 'edad_desde', 'edad_hasta',
            'created_at', 'updated_at',
        ]));
        $this->assertTrue(Schema::hasColumns('indicador_proyecto', [
            'id', 'proyecto_id', 'indicador_id', 'estatus', 'meta_cuantitativa', 'meta_cualitativa',
            'created_at', 'updated_at',
        ]));
    }

    public function test_an_indicator_cannot_be_assigned_twice_to_the_same_project(): void
    {
        $donanteId = DB::table('donantes')->insertGetId([
            'nombre' => 'Donante de prueba',
            'estatus' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $proyectoId = DB::table('proyectos')->insertGetId([
            'donante_id' => $donanteId,
            'estatus' => true,
            'codigo' => 'PROY-001',
            'descripcion' => 'Proyecto de prueba',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $indicadorId = DB::table('indicadores')->insertGetId([
            'codigo' => 'GCLPR/TEST',
            'descripcion' => 'Indicador de prueba',
            'unidad_conteo' => 'Personas',
            'espacio_coordinacion' => 'NNA',
            'edad_desde' => 0,
            'edad_hasta' => 120,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $assignment = [
            'proyecto_id' => $proyectoId,
            'indicador_id' => $indicadorId,
            'estatus' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        DB::table('indicador_proyecto')->insert($assignment);

        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);
        DB::table('indicador_proyecto')->insert($assignment);
    }
}
