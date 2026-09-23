<?php

namespace App\Http\Requests;

use App\Models\Report;

class UpdateReportRequest extends StoreReportRequest
{
    public function authorize(): bool
    {
        $report = $this->route('report');

        return $this->user() !== null
            && $report instanceof Report
            && $this->user()->canManageGroupReport($report);
    }

    public function rules(): array
    {
        $rules = parent::rules();

        foreach (array_keys($rules) as $field) {
            if ($field === 'beneficiaries' || str_starts_with($field, 'beneficiaries.')) {
                unset($rules[$field]);
            }
        }

        $rules['beneficiary'] = ['prohibited'];
        $rules['beneficiaries'] = ['prohibited'];

        return $rules;
    }

    public function withValidator($validator): void
    {
        parent::withValidator($validator);
        $validator->after(function ($validator): void {
            $report = $this->route('report');
            if ($this->integer('indicador_proyecto_id') === (int) $report->indicador_proyecto_id) {
                return;
            }
            $indicator = \App\Models\IndicadorProyecto::find($this->integer('indicador_proyecto_id'))?->indicador;
            if ($indicator?->unidad_conteo === 'Personas' && $report->beneficiaries()->where(function ($query) use ($indicator): void {
                $query->where('age', '<', $indicator->edad_desde)->orWhere('age', '>', $indicator->edad_hasta);
            })->exists()) {
                $validator->errors()->add('indicador_proyecto_id', 'El indicador no admite la edad de todos los beneficiarios del grupo. Edite individualmente a quienes corresponda.');
            }
        });
    }
}
