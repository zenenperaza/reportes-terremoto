<?php

namespace Database\Seeders;

use App\Models\Actividad;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ActividadSeeder extends Seeder
{
    public function run(): void
    {
        $actividades = [
            ['codigo' => 'ACT-1.1', 'descripcion' => 'Gestión y acompañamiento especializado para la restitución del derecho a la identidad.'],
            ['codigo' => 'ACT-1.2', 'descripcion' => 'Sensibilización comunitaria sobre la importancia del registro oportuno y la nacionalidad como base para la inclusión social.'],
            ['codigo' => 'ACT-1.3', 'descripcion' => 'Sensibilizar y capacitar a líderes(as) en las comunidades en temas de protección de NNA y el derecho a la obtención de documentos de identidad.'],
            ['codigo' => 'ACT-1.4', 'descripcion' => 'Proveer dotaciones de asistencia material a las instituciones del Estado competentes en materia de protección de NNA (papelería y artículos de oficina).'],
            ['codigo' => 'ACT-2.1', 'descripcion' => 'Gestión de casos y atención de emergencia para NNA sobrevivientes de VBG y otras formas de violencia/explotación.'],
            ['codigo' => 'ACT-2.2', 'descripcion' => 'Implementación de apoyo psicosocial grupal mediante la metodología de Espacios Amigables.'],
            ['codigo' => 'ACT-2.3', 'descripcion' => 'Proveer dotaciones de asistencia material a las instituciones del Estado competentes en materia de protección de NNA (equipos tecnológicos).'],
            ['codigo' => 'ACT-3.1', 'descripcion' => 'Provisión de asistencia legal especializada para el acceso a derechos y servicios de protección.'],
            ['codigo' => 'ACT-4.1', 'descripcion' => 'Recolección de opiniones, quejas y preguntas mediante mecanismos de retroalimentación establecidos.'],
        ];

        DB::transaction(function () use ($actividades): void {
            // Conserva las relaciones existentes de las tres actividades equivalentes.
            foreach (['ACT-1' => 'ACT-1.1', 'ACT-2' => 'ACT-1.2', 'ACT-3' => 'ACT-3.1'] as $legacy => $nuevo) {
                if (! Actividad::where('codigo', $nuevo)->exists()) {
                    Actividad::where('codigo', $legacy)->update(['codigo' => $nuevo]);
                }
            }

            foreach ($actividades as $actividad) {
                Actividad::updateOrCreate(
                    ['codigo' => $actividad['codigo']],
                    ['descripcion' => $actividad['descripcion']],
                );
            }

            $codigos = array_column($actividades, 'codigo');
            Actividad::whereNotIn('codigo', $codigos)
                ->whereDoesntHave('asignacionesIndicadores')
                ->delete();
        });
    }
}
