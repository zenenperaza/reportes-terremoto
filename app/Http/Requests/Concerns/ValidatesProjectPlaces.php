<?php

namespace App\Http\Requests\Concerns;

use App\Models\Proyecto;

trait ValidatesProjectPlaces
{
    protected function validateProjectPlace($validator, ?Proyecto $project, bool $preserveLocation): void
    {
        // Historical snapshots can still be kept verbatim. Community locations
        // are generated from a parish, not selected from the places catalog.
        if (! $project || $preserveLocation || $this->boolean('is_community_location') || $validator->errors()->has('place_name')) {
            return;
        }

        if (! $project->lugares()->where('place_names.name', $this->input('place_name'))->exists()) {
            $validator->errors()->add('place_name', 'El lugar seleccionado no está asignado al proyecto. Solicite al administrador que configure los lugares del proyecto.');
        }
    }
}
