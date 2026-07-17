<?php

namespace App\Http\Requests;

use App\Models\Activity;
use App\Models\Municipality;
use App\Models\Parish;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'report_date' => ['required', 'date', 'before_or_equal:today'],
            'reporter_first_name' => ['required', 'string', 'max:100'],
            'reporter_last_name' => ['required', 'string', 'max:100'],
            'reporter_email' => ['required', 'email', 'max:255'],
            'organization' => ['required', Rule::in(config('reports.organizations'))],
            'other_organization' => ['nullable', 'required_if:organization,Otro Socio Implementador', 'string', 'max:150'],

            'state_id' => ['required', 'integer', 'exists:states,id'],
            'municipality_id' => ['required', 'integer', 'exists:municipalities,id'],
            'parish_id' => ['required', 'integer', 'exists:parishes,id'],
            'installation_type' => ['required', Rule::in(config('reports.installation_types'))],
            'place_name' => ['required', 'string', 'max:200'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'altitude' => ['nullable', 'numeric', 'between:-500,10000'],
            'gps_accuracy' => ['nullable', 'numeric', 'min:0', 'max:100000'],

            'sector_id' => ['required', 'integer', 'exists:sectors,id'],
            'activity_id' => ['required', 'integer', 'exists:activities,id'],
            'activity_details' => ['nullable', 'string', 'max:5000'],
            'recurrence_status' => ['required', Rule::in(['recurrente', 'no_recurrente'])],
            'total_beneficiaries' => ['required', 'integer', 'min:0', 'max:10000000'],
            'beneficiary_scheme' => ['required', Rule::in(array_keys(config('reports.breakdown_schemes')))],
            'beneficiary_breakdown' => ['required', 'array', 'min:1'],
            'beneficiary_breakdown.*' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'people_with_disabilities' => ['nullable', 'integer', 'min:0', 'lte:total_beneficiaries'],
            'indigenous_people' => ['nullable', 'integer', 'min:0', 'lte:total_beneficiaries'],
            'pregnant_or_lactating_women' => ['nullable', 'integer', 'min:0', 'lte:total_beneficiaries'],

            'qualitative_notes' => ['nullable', 'string', 'max:5000'],
            'evidence_1' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx,xlsx', 'max:10240'],
            'evidence_2' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx,xlsx', 'max:10240'],
            'evidence_3' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx,xlsx', 'max:10240'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $municipality = Municipality::find($this->integer('municipality_id'));
            if ($municipality && $municipality->state_id !== $this->integer('state_id')) {
                $validator->errors()->add('municipality_id', 'El municipio no pertenece al estado seleccionado.');
            }

            $parish = Parish::find($this->integer('parish_id'));
            if ($parish && $parish->municipality_id !== $this->integer('municipality_id')) {
                $validator->errors()->add('parish_id', 'La parroquia no pertenece al municipio seleccionado.');
            }

            $activity = Activity::find($this->integer('activity_id'));
            if ($activity && $activity->sector_id !== $this->integer('sector_id')) {
                $validator->errors()->add('activity_id', 'La actividad no corresponde al sector seleccionado.');
            }

            $breakdown = collect($this->input('beneficiary_breakdown', []))
                ->filter(fn ($value) => $value !== null && $value !== '')
                ->sum(fn ($value) => (int) $value);

            if ($breakdown !== (int) $this->input('total_beneficiaries', 0)) {
                $validator->errors()->add(
                    'total_beneficiaries',
                    "La suma de la desagregación ({$breakdown}) debe coincidir con el total de beneficiarios.",
                );
            }
        });
    }

    public function attributes(): array
    {
        return [
            'report_date' => 'fecha del reporte',
            'state_id' => 'estado',
            'municipality_id' => 'municipio',
            'parish_id' => 'parroquia',
            'activity_id' => 'actividad a reportar',
            'total_beneficiaries' => 'total de beneficiarios',
        ];
    }
}
