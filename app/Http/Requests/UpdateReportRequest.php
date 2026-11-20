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
            if ($validator->errors()->has('report_date')) return;
            $date = \Carbon\CarbonImmutable::parse($this->input('report_date'))->startOfDay();
            if ($date->isSameDay($report->report_date) && $this->integer('indicador_proyecto_id') === (int) $report->indicador_proyecto_id) return;
            $indicator = \App\Models\IndicadorProyecto::find($this->integer('indicador_proyecto_id'))?->indicador;
            foreach ($report->beneficiaries as $person) {
                if ($person->birth_date && $person->birth_date->greaterThan($date)) {
                    $validator->errors()->add('report_date', 'La fecha de atención no puede ser anterior al nacimiento de un beneficiario.');
                    break;
                }
                $age = $person->birth_date ? (int) $person->birth_date->diffInYears($date) : $person->age;
                if ($age > 120 || ($indicator?->unidad_conteo === 'Personas'
                    && ($age < $indicator->edad_desde || $age > $indicator->edad_hasta))) {
                    $validator->errors()->add('indicador_proyecto_id', 'El indicador no admite la edad de todos los beneficiarios del grupo.');
                    break;
                }
            }
        });
    }
}
