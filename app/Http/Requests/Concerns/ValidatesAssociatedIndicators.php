<?php

namespace App\Http\Requests\Concerns;

use App\Models\IndicadorProyecto;

trait ValidatesAssociatedIndicators
{
    protected function associatedIndicatorRules(): array
    {
        return [
            'associated_indicator_ids' => [$this->routeIs('reports.store', 'beneficiaries.store') ? 'nullable' : 'prohibited', 'array', 'max:100'],
            'associated_indicator_ids.*' => ['required', 'integer', 'distinct', 'exists:indicador_proyecto,id'],
        ];
    }

    protected function validateAssociatedIndicators($validator): void
    {
        if ($validator->errors()->isNotEmpty()) return;
        $ids = $this->input('associated_indicator_ids', []) ?? [];
        if (!$ids) return;
        $principal = IndicadorProyecto::find($this->integer('indicador_proyecto_id'));
        $available = $principal?->indicadoresAsociados()->with('indicador')
            ->where('indicador_proyecto.proyecto_id', $this->integer('proyecto_id'))
            ->where('indicador_proyecto.estatus', true)->whereNotNull('sector_proyecto_id')
            ->where('indicador_proyecto.id', '!=', $principal->id)->get() ?? collect();
        if (collect($ids)->map(fn ($id) => (int) $id)->diff($available->pluck('id'))->isNotEmpty()) {
            $validator->errors()->add('associated_indicator_ids', 'Seleccione únicamente asociados activos del indicador y proyecto elegidos.');
            return;
        }
        $people = $this->input('beneficiaries', [$this->input('beneficiary', [])]);
        foreach ($available->whereIn('id', $ids) as $assignment) {
            $indicator = $assignment->indicador;
            if ($indicator->unidad_conteo !== 'Personas') continue;
            foreach ($people as $person) {
                if (isset($person['age']) && ($person['age'] < $indicator->edad_desde || $person['age'] > $indicator->edad_hasta)) {
                    $validator->errors()->add('associated_indicator_ids', "El asociado {$indicator->codigo} admite edades entre {$indicator->edad_desde} y {$indicator->edad_hasta} años. No se guardó ningún registro.");
                    break;
                }
            }
        }
    }
}
