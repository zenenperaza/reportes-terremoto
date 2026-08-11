<?php

namespace Database\Seeders;

use App\Models\Servicio;
use Illuminate\Database\Seeder;

class ServicioSeeder extends Seeder
{
    public function run(): void
    {
        $servicios = [
            'Localización Familiar',
            'Asistencia de Documentación',
            'Evaluación y Reunificación Familiar',
            'Evaluación Psicosocial',
            'Apoyo para Reencuentro Familiar',
            'Asistencia y Seguimiento de la Familia',
            'Traslado humanitario interno',
            'Formación a líderes e Instituciones',
            'KITS DE HIGIENE (NNA)',
            'VIATICOS ALIMENTOS',
            'TRASLADO (NNA)',
            'ORIENTACION',
            'ORIENTACION LEGAL',
            'KITS DE ALIMENTACIÓN (ASONACOP)',
        ];

        foreach ($servicios as $servicio) {
            Servicio::updateOrCreate(
                ['nombre' => $servicio],
                ['descripcion' => $servicio],
            );
        }
    }
}
