<?php

namespace Tests\Concerns;

use App\Models\Donante;
use App\Models\Indicador;
use App\Models\IndicadorProyecto;
use App\Models\PlaceName;
use App\Models\Proyecto;
use App\Models\Sector;
use App\Models\SectorProyecto;
use App\Models\State;
use App\Models\User;

trait CreatesReportProjectContext
{
    /** New-record fixtures must now select an assigned project, sector and indicator. */
    private function reportProjectContext(User $owner, State $state, Sector $sector): array
    {
        $number = Proyecto::count() + 1;
        $donor = Donante::firstOrCreate(['nombre' => 'Donante de prueba'], ['estatus' => true]);
        $project = Proyecto::create(['donante_id' => $donor->id, 'codigo' => 'TEST-PROJECT-'.$number,
            'descripcion' => 'Proyecto de prueba', 'estatus' => true]);
        $project->estados()->attach($state);
        // Fixtures explicitly grant their catalog places; production never defaults to all.
        $project->lugares()->sync(PlaceName::pluck('id')->all());
        $owner->projects()->attach($project);
        $owner->assignedStates()->syncWithoutDetaching([$state->id]);
        $projectSector = SectorProyecto::create(['proyecto_id' => $project->id, 'sector_id' => $sector->id]);
        $indicator = Indicador::create(['codigo' => 'TEST-IND-'.$number, 'descripcion' => 'Indicador de prueba',
            'unidad_conteo' => 'Personas', 'espacio_coordinacion' => 'NNA', 'edad_desde' => 0, 'edad_hasta' => 120]);
        $assignment = IndicadorProyecto::create(['proyecto_id' => $project->id, 'sector_proyecto_id' => $projectSector->id,
            'indicador_id' => $indicator->id, 'estatus' => true]);

        return ['proyecto_id' => $project->id, 'sector_proyecto_id' => $projectSector->id, 'indicador_proyecto_id' => $assignment->id];
    }
}
